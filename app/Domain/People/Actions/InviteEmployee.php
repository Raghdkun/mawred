<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\UserInvitation;
use App\Domain\People\Notifications\EmployeeInvitationNotification;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invites an employee to create their account. Any earlier pending invitation is revoked; only
 * the token hash is stored. Returns the link so HR can also share it directly.
 */
final readonly class InviteEmployee
{
    public const int TTL_DAYS = 7;

    public function __construct(private Settings $settings) {}

    /**
     * @return array{invitation: UserInvitation, url: string}
     */
    public function handle(Actor $actor, Employee $employee, ?string $email = null, ?string $locale = null): array
    {
        if ($employee->user_id !== null) {
            throw new DomainRuleViolation('people.already_has_account', __('This employee already has an account.'));
        }

        $email = mb_strtolower(trim($email ?? $employee->work_email ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainRuleViolation('people.invite_email_required', __('Add a work email or enter an address to send the invitation to.'), ['email' => [__('Add a work email or enter an address to send the invitation to.')]]);
        }

        if (User::withTrashed()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new DomainRuleViolation('people.account_exists', __('An account with this email already exists. Link it to the employee instead.'), ['email' => [__('An account with this email already exists. Link it to the employee instead.')]]);
        }

        UserInvitation::query()->where('employee_id', $employee->id)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $token = Str::random(48);
        $invitation = UserInvitation::query()->create([
            'employee_id' => $employee->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'invited_by' => $actor->user?->id,
        ]);

        $url = route('invitations.show', ['token' => $token]);
        $locale ??= is_string($default = $this->settings->get('locales.default')) ? $default : 'en';
        $company = $this->settings->get('company.name');
        $companyName = is_array($company) ? ($company[$locale] ?? $company['en'] ?? null) : null;
        $companyName = is_string($companyName) && $companyName !== '' ? $companyName : config()->string('app.name');

        Notification::route('mail', $email)->notify(new EmployeeInvitationNotification(
            url: $url,
            employeeName: $employee->displayName($locale),
            companyName: $companyName,
            validDays: self::TTL_DAYS,
            mailLocale: $locale,
        ));

        return ['invitation' => $invitation, 'url' => $url];
    }
}
