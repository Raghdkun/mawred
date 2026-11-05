<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Actions\CreateAdminAccount;
use App\Domain\Access\Actions\IssuePasswordSetupLink;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DefaultRoles;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('mawred:create-admin {--name= : Full name} {--email= : Sign-in email} {--role='.DefaultRoles::SUPER_ADMIN.' : Role key, e.g. super_admin or hr_admin} {--relink : Issue a new password link for an existing account}')]
#[Description('Create an admin account (D136) and print a one-time link where they choose their password')]
final class CreateAdmin extends Command
{
    public function handle(CreateAdminAccount $create, IssuePasswordSetupLink $link): int
    {
        if (! Role::query()->exists()) {
            $this->components->error('There are no roles yet. Run php artisan mawred:install first.');

            return self::FAILURE;
        }

        $email = $this->stringOption('email') ?? text('Email', required: true, validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');

        if ($this->option('relink')) {
            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

            if (! $user instanceof User) {
                $this->components->error("There is no account for {$email}.");

                return self::FAILURE;
            }

            $this->printLink($link->handle($user));

            return self::SUCCESS;
        }

        $name = $this->stringOption('name') ?? text('Full name', required: true);
        $role = $this->stringOption('role') ?? DefaultRoles::SUPER_ADMIN;

        try {
            $user = $create->handle(Actor::system('console'), $name, $email, $role);
        } catch (DomainRuleViolation $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$user->email} can now sign in as {$role} once they choose a password.");
        $this->printLink($link->handle($user));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function printLink(string $url): void
    {
        $this->components->twoColumnDetail('One-time password link (valid '.config()->integer('auth.passwords.users.expire').' min)', $url);
        $this->components->warn('Send it only to them. Run again with --relink --email=… if it expires.');
    }
}
