<?php

namespace Tests\Unit;

use App\Services\ChapterReportService;
use PHPUnit\Framework\TestCase;

class ChapterTotalsTest extends TestCase
{
    public function test_chapter_rows_roll_up_into_association_wide_totals(): void
    {
        $rows = collect([
            ['events' => 4, 'capacity' => 200, 'tickets' => 150, 'tickets_started' => 100, 'checked_in' => 80, 'revenue' => 30000.0],
            ['events' => 2, 'capacity' => 100, 'tickets' => 30, 'tickets_started' => 0, 'checked_in' => 0, 'revenue' => 4500.5],
        ]);

        $totals = (new ChapterReportService)->totals($rows);

        $this->assertSame(2, $totals['chapters']);
        $this->assertSame(6, $totals['events']);
        $this->assertSame(180, $totals['tickets']);
        $this->assertSame(300, $totals['capacity']);
        $this->assertSame(34500.5, $totals['revenue']);
        $this->assertSame(60.0, $totals['fill_rate']);
        $this->assertSame(80.0, $totals['attendance_rate']);
    }

    public function test_rates_are_zero_instead_of_dividing_by_zero_when_nothing_has_happened_yet(): void
    {
        $totals = (new ChapterReportService)->totals(collect());

        $this->assertSame(0, $totals['chapters']);
        $this->assertSame(0.0, $totals['fill_rate']);
        $this->assertSame(0.0, $totals['attendance_rate']);
    }
}
