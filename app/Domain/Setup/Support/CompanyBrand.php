<?php

declare(strict_types=1);

namespace App\Domain\Setup\Support;

use App\Domain\Forms\Support\FieldSpec;
use Illuminate\Support\Facades\Storage;

/** The company's name and logo for printed documents (offer letters, certificates). */
final readonly class CompanyBrand
{
    public function __construct(private Settings $settings) {}

    public function name(string $locale): string
    {
        return FieldSpec::text($this->settings->get('company.name'), $locale, FieldSpec::string(config('app.name'), 'Mawred'));
    }

    /** The logo as a data URI, so a PDF needs nothing from the network. */
    public function logoDataUri(): ?string
    {
        $path = $this->settings->get('company.logo_path');

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
