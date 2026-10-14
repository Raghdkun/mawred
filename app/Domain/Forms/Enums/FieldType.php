<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

/**
 * MFS v1 field types (D51). Values are what the requester submits and what is stored.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Money = 'money';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case DateRange = 'date_range';
    case Time = 'time';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';
    case Boolean = 'boolean';
    case File = 'file';
    case Employee = 'employee';
    case Department = 'department';
    case Branch = 'branch';
    case LeaveType = 'leave_type';
    case Calculated = 'calculated';
    case Note = 'note';

    /** Display-only fields carry no value. */
    public function hasValue(): bool
    {
        return $this !== self::Note;
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect, self::Radio, self::Checkboxes], true);
    }

    public function isMulti(): bool
    {
        return in_array($this, [self::Multiselect, self::Checkboxes], true);
    }

    /** Pickers search the directory, so they need a signed-in requester (not on public forms). */
    public function isPicker(): bool
    {
        return in_array($this, [self::Employee, self::Department, self::Branch, self::LeaveType], true);
    }

    public function acceptsInput(): bool
    {
        return $this->hasValue() && $this !== self::Calculated;
    }
}
