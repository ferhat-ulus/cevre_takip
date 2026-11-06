<?php

namespace Tests\Feature;

use App\Models\Firm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmissionProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_emission_page_separates_permit_basis_dates_from_confirmation_measurement(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-dates',
            'data' => [
                'name' => 'Emisyon Tarihleri Tesisi',
                'consultant' => 'DANIŞMAN',
                'items' => [
                    'izneEsasEmisyonOlcum' => ['s' => 'tarih', 'd' => '2022-03-10'],
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2025-03-10'],
                    'emisyonRapor' => ['s' => 'tarih', 'd' => '2022-04-02'],
                    'emisyonTeyitRaporu' => ['s' => 'tarih', 'd' => '2025-03-12'],
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get('/emisyon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('firms.0.name', 'Emisyon Tarihleri Tesisi')
                    ->where('firms.0.izneEsasEmisyonOlcumDate', '2022-03-10')
                    ->where('firms.0.emisyonRapor.recordedDate', '2022-04-02')
                    ->where('firms.0.emisyonOlcum.recordedDate', '2025-03-10')
                    ->where('firms.0.emisyonOlcum.basisDate', '2025-03-10')
                    ->where('firms.0.emisyonOlcum.isPermitBasis', false)
                    ->where('firms.0.emisyonOlcum.due', Carbon::parse('2025-03-10')->addDays(720)->toDateString())
                    ->where('firms.0.emisyonTeyitRaporu.recordedDate', '2025-03-12')
                    ->where('firms.0.emisyonTeyitRaporu.due', Carbon::parse('2025-03-12')->addDays(720)->toDateString())
                    ->where('firms.0.emisyonTeyitRaporu.state', 'ok')
                    ->where('firms.0.overallState', 'ok')
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_confirmation_period_starts_from_permit_basis_measurement_until_first_confirmation(): void
    {
        Carbon::setTestNow('2026-01-01 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-baseline-fallback',
            'data' => [
                'name' => 'İzne Esas Ölçüm Başlangıç Tesisi',
                'items' => [
                    'izneEsasEmisyonOlcum' => ['s' => 'tarih', 'd' => '2024-01-01'],
                    'emisyonOlcum' => ['s' => null],
                    'emisyonRapor' => ['s' => 'tarih', 'd' => '2024-02-01'],
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get('/emisyon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('firms.0.emisyonOlcum.recordedDate', null)
                    ->where('firms.0.emisyonOlcum.basisDate', '2024-01-01')
                    ->where('firms.0.emisyonOlcum.isPermitBasis', true)
                    ->where('firms.0.emisyonOlcum.due', Carbon::parse('2024-01-01')->addDays(720)->toDateString())
                    ->where('firms.0.emisyonOlcum.state', 'overdue')
                    ->where('firms.0.emisyonRapor.recordedDate', '2024-02-01')
                    ->where('firms.0.emisyonTeyitRaporu.recordedDate', null)
                    ->where('firms.0.emisyonTeyitRaporu.isPermitBasis', true)
                    ->where('firms.0.emisyonTeyitRaporu.basisDate', '2024-02-01')
                    ->where('firms.0.emisyonTeyitRaporu.due', Carbon::parse('2024-02-01')->addDays(720)->toDateString())
                    ->where('firms.0.emisyonTeyitRaporu.state', 'crit')
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_missing_permit_and_confirmation_dates_are_reported_without_using_report_date_for_teyit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-no-date',
            'data' => [
                'name' => 'Tarihsiz Emisyon Tesisi',
                'items' => [
                    'emisyonOlcum' => ['s' => null],
                    'emisyonRapor' => ['s' => 'tarih', 'd' => '2025-04-02'],
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get('/emisyon')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firms.0.izneEsasEmisyonOlcumDate', null)
                ->where('firms.0.emisyonOlcum.recordedDate', null)
                ->where('firms.0.emisyonOlcum.due', null)
                ->where('firms.0.overallState', 'overdue')
                ->where('firms.0.emisyonRapor.recordedDate', '2025-04-02')
            );
    }

    public function test_confirmation_measurement_alerts_use_the_day_period_and_configured_warning_windows(): void
    {
        $due = Carbon::parse('2024-06-08')->addDays(720);
        Carbon::setTestNow($due->copy()->subDays(90)->setTime(12, 0));
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-two-years',
            'data' => [
                'name' => 'İki Yıllık Teyit Tesisi',
                'items' => [
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2024-06-08'],
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get('/emisyon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('firms.0.emisyonOlcum.due', $due->toDateString())
                    ->where('firms.0.emisyonOlcum.days', 90)
                    ->where('firms.0.emisyonOlcum.state', 'soon')
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_confirmation_measurement_becomes_critical_in_the_last_sixty_days(): void
    {
        $due = Carbon::parse('2024-06-08')->addDays(720);
        Carbon::setTestNow($due->copy()->subDays(60)->setTime(12, 0));
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-critical-window',
            'data' => [
                'name' => 'Kritik Teyit Tesisi',
                'items' => [
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2024-06-08'],
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get('/emisyon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('firms.0.emisyonOlcum.due', $due->toDateString())
                    ->where('firms.0.emisyonOlcum.days', 60)
                    ->where('firms.0.emisyonOlcum.state', 'crit')
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_confirmation_report_warning_is_independent_from_the_measurement_warning(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'emission-split-warnings',
            'data' => [
                'name' => 'Ayrı Uyarı Tesisi',
                'items' => [
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2026-01-01'],
                    'emisyonTeyitRaporu' => ['s' => 'tarih', 'd' => '2024-01-01'],
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get('/emisyon')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('firms.0.emisyonOlcum.state', 'ok')
                    ->where('firms.0.emisyonOlcum.due', Carbon::parse('2026-01-01')->addDays(720)->toDateString())
                    ->where('firms.0.emisyonTeyitRaporu.state', 'overdue')
                    ->where('firms.0.emisyonTeyitRaporu.due', Carbon::parse('2024-01-01')->addDays(720)->toDateString())
                );
        } finally {
            Carbon::setTestNow();
        }
    }
}
