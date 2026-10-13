<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Wizard step 2 (brief §4): company name (EN/AR), logo and accent. The logo and accent appear
 * only in co-brand areas (D22). Logos are raster only: SVG uploads could carry script.
 */
final readonly class SaveCompanyProfile
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{en: string, ar: string}  $name
     */
    public function handle(Actor $actor, array $name, ?string $legalName, ?string $accent, ?UploadedFile $logo, bool $removeLogo = false): void
    {
        $this->settings->set('company.name', $name);
        $this->settings->set('company.legal_name', $legalName);
        $this->settings->set('company.accent', $accent);

        $current = $this->settings->get('company.logo_path');

        if (($logo instanceof UploadedFile || $removeLogo) && is_string($current)) {
            Storage::disk('public')->delete($current);
            $this->settings->forget('company.logo_path');
        }

        if ($logo instanceof UploadedFile) {
            $path = $logo->storeAs('branding', 'logo-'.Str::lower((string) Str::ulid()).'.'.$logo->extension(), 'public');
            $this->settings->set('company.logo_path', $path);
        }

        activity('setup')->causedBy($actor->user)->event('company.saved')
            ->withProperties(['name' => $name, 'logo_changed' => $logo instanceof UploadedFile || $removeLogo])
            ->log('Company profile saved');
    }
}
