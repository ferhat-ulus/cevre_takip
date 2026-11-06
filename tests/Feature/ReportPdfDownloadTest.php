<?php

namespace Tests\Feature;

use App\Models\Firm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_missing_data_as_the_highest_priority_warning(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Rapor Yöneticisi']);
        Firm::create([
            'id' => 'missing-data-report',
            'data' => [
                'name' => 'Eksik Veri Rapor Tesisi',
                'consultant' => 'Danışman',
                'items' => ['emisyonOlcum' => ['s' => 'tarih', 'd' => '2020-01-01']],
                'profile' => [],
            ],
        ]);

        $this->actingAs($admin)
            ->get('/raporlar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.overdue', fn ($count) => $count > 0)
                ->where('stats.missing', fn ($count) => $count > 0)
                ->where('consultantStats.Danışman.overdue', fn ($count) => $count > 0)
                ->where('workItems', fn ($items) => collect($items)->contains(fn ($item) => $item['state'] === 'overdue')
                    && collect($items)->contains(fn ($item) => $item['state'] === 'missing'))
                ->where('riskyFirms.0.issues.0.color', 'missing')
            );
    }

    public function test_admin_can_download_a_pdf_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Rapor Yöneticisi']);
        foreach (range(1, 101) as $number) {
            Firm::create([
                'id' => sprintf('pdf-report-%03d', $number),
                'data' => [
                    'name' => "PDF Rapor Tesisi {$number}",
                    'consultant' => 'Danışman',
                    'items' => [],
                    'profile' => [],
                ],
            ]);
        }

        $response = $this->actingAs($admin)->get('/api/export-pdf?consultant=all');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('cevre-takip-raporu-' . Carbon::today()->format('Y-m-d') . '.pdf');
    }

    public function test_consultant_can_download_only_their_report(): void
    {
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        Firm::create([
            'id' => 'pdf-consultant-firm',
            'data' => [
                'name' => 'Danışman PDF Tesisi',
                'consultant' => $consultant->name,
                'items' => [],
                'profile' => [],
            ],
        ]);

        $response = $this->actingAs($consultant)->get('/api/export-pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('cevre-takip-raporu-' . Carbon::today()->format('Y-m-d') . '.pdf');
    }
}
