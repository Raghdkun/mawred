<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * HTML → PDF through Gotenberg's Chromium route (ADR-0019). The HTML must be self-contained
 * (inline styles, data: images); nothing is fetched from the network while printing.
 */
final readonly class Gotenberg
{
    public function __construct(private Http $http) {}

    /** Prints A4, portrait unless `$landscape`; full-bleed artwork (certificates, D110) passes no margin. */
    public function html(string $html, bool $landscape = false, string $margin = '0.6'): string
    {
        $url = config('services.gotenberg.url');

        $response = $this->http->timeout(30)
            ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html'])
            ->post(rtrim(is_string($url) ? $url : '', '/').'/forms/chromium/convert/html', [
                'paperWidth' => $landscape ? '11.7' : '8.27',
                'paperHeight' => $landscape ? '8.27' : '11.7',
                'marginTop' => $margin,
                'marginBottom' => $margin,
                'marginLeft' => $margin,
                'marginRight' => $margin,
                'printBackground' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('The PDF service could not print the document (HTTP '.$response->status().').');
        }

        return $response->body();
    }
}
