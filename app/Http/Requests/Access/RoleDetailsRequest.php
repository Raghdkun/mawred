<?php

declare(strict_types=1);

namespace App\Http\Requests\Access;

use App\Domain\Access\Enums\HomeLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RoleDetailsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label.en' => ['required', 'string', 'max:80'],
            'label.ar' => ['required', 'string', 'max:80'],
            'description.en' => ['nullable', 'string', 'max:300'],
            'description.ar' => ['nullable', 'string', 'max:300'],
            'requiresTwoFactor' => ['sometimes', 'boolean'],
            'homeLayout' => ['sometimes', Rule::enum(HomeLayout::class)],
        ];
    }

    /**
     * @return array{en: string, ar: string}
     */
    public function label(): array
    {
        return ['en' => $this->string('label.en')->trim()->toString(), 'ar' => $this->string('label.ar')->trim()->toString()];
    }

    /**
     * @return array{en: string|null, ar: string|null}
     */
    public function description(): array
    {
        return [
            'en' => $this->filled('description.en') ? $this->string('description.en')->trim()->toString() : null,
            'ar' => $this->filled('description.ar') ? $this->string('description.ar')->trim()->toString() : null,
        ];
    }
}
