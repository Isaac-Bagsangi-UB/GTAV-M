<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Small wrapper around bacon/bacon-qr-code. SVG output needs no extra PHP extensions. */
class QrCode
{
    public static function svg(string $text, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($text);
    }

    /** For <img src="..."> inside PDFs and pages. */
    public static function dataUri(string $text, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }
}
