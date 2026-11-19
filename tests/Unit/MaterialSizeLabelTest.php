<?php

namespace Tests\Unit;

use App\Models\Material;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaterialSizeLabelTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function sizes(): array
    {
        return [
            'tiny files still show 1 KB' => [12, '1 KB'],
            'kilobytes are rounded' => [1536, '2 KB'],
            'just under a megabyte' => [1048575, '1024 KB'],
            'exactly one megabyte' => [1048576, '1.0 MB'],
            'megabytes keep one decimal' => [5_767_168, '5.5 MB'],
        ];
    }

    #[DataProvider('sizes')]
    public function test_file_sizes_are_shown_in_readable_units(int $bytes, string $expected): void
    {
        $material = new Material;
        $material->size = $bytes;

        $this->assertSame($expected, $material->sizeLabel());
    }
}
