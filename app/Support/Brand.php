<?php

namespace App\Support;

/**
 * Brand assets for generated PDFs.
 * The logo is embedded as a data URI so dompdf never needs file-system or network access to find it.
 * Both images sit on the site's #0F0F0F black, so they blend into the black header bands of every PDF.
 */
class Brand
{
    private static array $cache = [];

    /** Globe and wordmark, for headers (600 x 285 px). */
    public static function markDataUri(): string
    {
        return self::dataUri('images/logo-print-mark.jpg');
    }

    /** Full emblem with the "Global Trade Association Events Manager" line (900 x 513 px). */
    public static function fullDataUri(): string
    {
        return self::dataUri('images/logo-print.jpg');
    }

    private static function dataUri(string $relativePath): string
    {
        if (! isset(self::$cache[$relativePath])) {
            $path = public_path($relativePath);
            self::$cache[$relativePath] = is_file($path)
                ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($path))
                : '';
        }

        return self::$cache[$relativePath];
    }
}
