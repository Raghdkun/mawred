<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Employee number prefix (D38), e.g. `EMP-` → EMP-000123. Existing numbers never change.
 */
final readonly class SaveNumberingSettings
{
    public function __construct(private Settings $settings) {}

    public function handle(Actor $actor, string $prefix): void
    {
        $prefix = mb_strtoupper(trim($prefix));

        if (preg_match('/^[A-Z0-9][A-Z0-9_-]{0,9}$/', $prefix) !== 1) {
            throw new DomainRuleViolation('people.prefix_invalid', __('Use up to 10 letters, digits, - or _.'), ['prefix' => [__('Use up to 10 letters, digits, - or _.')]]);
        }

        $previous = $this->settings->get('people.number_prefix');
        $this->settings->set('people.number_prefix', $prefix);

        activity('settings')->causedBy($actor->user)->event('people.numbering')
            ->withProperties(['old' => $previous, 'new' => $prefix])->log('Employee number prefix changed');
    }
}
