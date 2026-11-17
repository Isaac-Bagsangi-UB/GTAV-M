<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\ChapterReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_three_reports_download_as_pdfs_and_are_audited(): void
    {
        $event = Event::factory()->ended()->create();
        Registration::factory()->count(3)->for($event)->checkedIn()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('manage.reports.index'))->assertOk()->assertSee('Chapter Performance');

        $this->actingAs($admin)->get(route('manage.reports.chapter-performance'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('manage.reports.event-attendance', ['event_id' => $event->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('manage.reports.registration-revenue', [
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(3, AuditLog::where('action', 'report.generated')->where('user_id', $admin->id)->count());
    }

    public function test_chapter_managers_cannot_report_on_another_chapters_event(): void
    {
        $manager = User::factory()->chapterManager(Chapter::factory()->create())->create();
        $otherEvent = Event::factory()->create();

        $this->actingAs($manager)->get(route('manage.reports.event-attendance', ['event_id' => $otherEvent->id]))->assertForbidden();
    }

    public function test_the_revenue_report_rejects_a_period_that_ends_before_it_starts(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('manage.reports.registration-revenue', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_draft_events_do_not_count_toward_chapter_capacity(): void
    {
        $chapter = Chapter::factory()->create();
        Event::factory()->for($chapter)->capacity(40)->create();
        Event::factory()->for($chapter)->capacity(500)->draft()->create();

        $row = app(ChapterReportService::class)->chapters($chapter->id)->first();

        $this->assertSame(1, $row['events']);
        $this->assertSame(40, $row['capacity']);
    }
}
