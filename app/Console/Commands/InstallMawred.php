<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DefaultRoles;
use App\Domain\Setup\Actions\CreateOwnerAccount;
use App\Domain\Setup\Actions\IssueOwnerSetupLink;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('mawred:install {--name= : Owner name} {--email= : Owner email} {--relink : Issue a new setup link for the existing owner}')]
#[Description('Bootstrap a fresh install: roles, permissions and the first Super Admin with a one-time setup link')]
final class InstallMawred extends Command
{
    public function handle(CreateOwnerAccount $create, IssueOwnerSetupLink $link): int
    {
        if ($this->option('relink')) {
            $owner = User::role(DefaultRoles::SUPER_ADMIN)->where('status', 'invited')->oldest('id')->first();

            if (! $owner instanceof User) {
                $this->components->error('There is no invited Super Admin waiting to finish setup.');

                return self::FAILURE;
            }

            $this->printLink($link->handle($owner), $owner);

            return self::SUCCESS;
        }

        $name = $this->stringOption('name') ?? text('Owner name', required: true);
        $email = $this->stringOption('email') ?? text('Owner email', required: true, validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email.');

        try {
            $owner = $create->handle($name, $email);
        } catch (DomainRuleViolation $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printLink($link->handle($owner), $owner);

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function printLink(string $url, User $owner): void
    {
        $this->components->info("Super Admin {$owner->email} is ready.");
        $this->components->twoColumnDetail('One-time setup link (valid '.IssueOwnerSetupLink::TTL_HOURS.' h)', $url);
        $this->components->warn('Open it to set a password and enable two-factor authentication. Run with --relink if it expires.');
    }
}
