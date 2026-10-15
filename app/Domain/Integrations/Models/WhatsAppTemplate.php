<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A message template as Meta last reported it (synced, never edited here).
 *
 * @property int $id
 * @property string $name
 * @property string $language
 * @property string|null $category
 * @property string $status APPROVED|PENDING|REJECTED|PAUSED|DISABLED
 * @property list<array<string, mixed>> $components
 * @property CarbonImmutable $synced_at
 */
#[Guarded(['id'])]
#[Table(name: 'whatsapp_templates')]
final class WhatsAppTemplate extends Model
{
    /**
     * Number of {{n}} placeholders in the body.
     */
    public function bodyParameters(): int
    {
        foreach ($this->components as $component) {
            if (($component['type'] ?? null) === 'BODY' && is_string($component['text'] ?? null)) {
                return (int) preg_match_all('/\{\{\d+\}\}/', $component['text']);
            }
        }

        return 0;
    }

    /**
     * Quick-reply buttons, in order.
     *
     * @return list<string>
     */
    public function quickReplies(): array
    {
        $titles = [];

        foreach ($this->components as $component) {
            if (($component['type'] ?? null) === 'BUTTONS' && is_array($component['buttons'] ?? null)) {
                foreach ($component['buttons'] as $button) {
                    if (is_array($button) && ($button['type'] ?? null) === 'QUICK_REPLY' && is_string($button['text'] ?? null)) {
                        $titles[] = $button['text'];
                    }
                }
            }
        }

        return $titles;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['components' => 'array', 'synced_at' => 'immutable_datetime'];
    }
}
