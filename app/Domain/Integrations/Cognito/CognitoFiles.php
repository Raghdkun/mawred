<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Cognito;

use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cognito file links expire in about 30 minutes, so attachments are downloaded while the entry
 * is processed and stored like any other form upload (scanned, typed, sized).
 */
final readonly class CognitoFiles
{
    public const int MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private StoreFormUpload $store) {}

    /**
     * @return list<string> upload ids for the form field
     */
    public function download(mixed $value, Form $form, FormVersion $version, string $fieldKey, string $session): array
    {
        $files = is_array($value) && array_is_list($value) ? $value : (is_array($value) ? [$value] : []);
        $ids = [];

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $url = $file['File'] ?? $file['Url'] ?? $file['url'] ?? null;
            $name = $file['Name'] ?? $file['name'] ?? 'attachment';

            if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                continue;
            }

            $response = Http::timeout(30)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]])->get($url);

            if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES) {
                throw new RuntimeException("Could not download the file for {$fieldKey}.");
            }

            File::ensureDirectoryExists(storage_path('app/tmp'));
            $path = storage_path('app/tmp/cognito-'.Str::ulid());
            File::put($path, $response->body());
            $upload = $this->store->handle($form, $version, $fieldKey, new UploadedFile($path, is_string($name) ? basename($name) : 'attachment', null, null, true), null, $session);
            $ids[] = $upload->ulid;
            File::delete($path);
        }

        return $ids;
    }
}
