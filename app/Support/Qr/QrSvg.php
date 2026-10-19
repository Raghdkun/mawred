<?php

declare(strict_types=1);

namespace App\Support\Qr;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes as inline SVG (kiosks, training check-in, certificate verification). */
final class QrSvg
{
    public static function render(string $content, int $size = 320): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($content);
    }

    public static function dataUri(string $content, int $size = 320): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::render($content, $size));
    }
}
