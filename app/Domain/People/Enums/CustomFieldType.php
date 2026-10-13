<?php

declare(strict_types=1);

namespace App\Domain\People\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Boolean = 'boolean';
    case Email = 'email';
    case Phone = 'phone';
    case Url = 'url';

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }
}
