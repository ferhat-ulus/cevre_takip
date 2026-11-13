<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Firm;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_shows_firm_classification_counts_for_the_current_users_scope()
    {
        $user = User::factory()->create(['role' => 'employee', 'name' => 'Danışman A']);
        foreach ([
            ['name' => 'Ek 1', 'izinKonusu' => 'İZİN EK-1'],
            ['name' => 'Ek 2', 'izinKonusu' => 'İZİN LİSANS EK-2'],
            ['name' => 'Kapsam dışı', 'izinKonusu' => 'KAPSAMDIŞI-SIN.'],
            ['name' => 'Ek 2 kapsam dışı', 'izinKonusu' => 'EK-2 KAPSAMDIŞI'],
            ['name' => 'Sınıflandırılmamış', 'izinKonusu' => ''],
        ] as $index => $firm) {
            Firm::create([
                'id' => "dashboard-{$index}",
                'data' => [
                    'name' => $firm['name'],
                    'consultant' => 'Danışman A',
                    'profile' => ['izinKonusu' => $firm['izinKonusu']],
                ],
            ]);
        }
        Firm::create([
            'id' => 'dashboard-other-consultant',
            'data' => [
                'name' => 'Başka danışman',
                'consultant' => 'Danışman B',
                'profile' => ['izinKonusu' => 'İZİN EK-1'],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firmSummary.total', 5)
                ->where('firmSummary.ek1', 1)
                ->where('firmSummary.ek2', 1)
                ->where('firmSummary.scopeOutside', 2)
            );
    }

    public function test_missing_data_is_counted_separately_and_does_not_fill_the_dated_work_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'dashboard-missing-priority',
            'data' => [
                'name' => 'Eksik Veri Öncelik Tesisi',
                'consultant' => 'Danışman A',
                'items' => [
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2020-01-01'],
                ],
                'profile' => [],
            ],
        ]);
        Firm::create([
            'id' => 'dashboard-other-dated',
            'data' => [
                'name' => 'Diğer Danışman Tesisi',
                'consultant' => 'Danışman B',
                'items' => [
                    'emisyonOlcum' => ['s' => 'tarih', 'd' => '2024-01-01'],
                ],
                'profile' => [],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.missing', fn ($count) => $count > 0)
                ->where('stats.overdue', fn ($count) => $count > 0)
                ->where('consultants', fn ($rows) => collect($rows)->contains(fn ($row) => $row['name'] === 'Danışman A'
                    && $row['missing'] > 0
                    && collect($row['tasks'])->every(fn ($task) => $task['state'] !== 'missing'))
                    && collect($rows)->contains(fn ($row) => $row['name'] === 'Danışman B'
                        && collect($row['tasks'])->contains(fn ($task) => $task['state'] === 'overdue')))
            );
    }

    public function test_consultant_sees_next_month_calendar_reminder_with_the_missing_firm_count_after_the_twentieth(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 10:00:00', 'Europe/Istanbul'));

        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'Takvim Danışmanı']);
        $plannedFirm = Firm::create([
            'id' => 'calendar-planned-firm',
            'consultant_user_id' => $consultant->id,
            'data' => ['name' => 'Planlı Tesis', 'profile' => []],
        ]);
        Firm::create([
            'id' => 'calendar-missing-firm',
            'consultant_user_id' => $consultant->id,
            'data' => ['name' => 'Eksik Tesis', 'profile' => []],
        ]);
        Visit::create([
            'firm_id' => $plannedFirm->id,
            'user_id' => $consultant->id,
            'visit_date' => '2026-12-03',
            'title' => 'Tesis Ziyareti',
        ]);

        $this->actingAs($consultant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visitPlanningReminder.visible', true)
                ->where('visitPlanningReminder.monthLabel', 'Aralık 2026')
                ->where('visitPlanningReminder.missingCount', 1));
    }

    public function test_calendar_reminder_is_hidden_before_the_twentieth_and_when_all_firms_are_planned(): void
    {
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'Planlı Danışman']);
        $firm = Firm::create([
            'id' => 'calendar-complete-firm',
            'consultant_user_id' => $consultant->id,
            'data' => ['name' => 'Tamam Tesis', 'profile' => []],
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00:00', 'Europe/Istanbul'));
        $this->actingAs($consultant)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('visitPlanningReminder.visible', false));

        Visit::create([
            'firm_id' => $firm->id,
            'user_id' => $consultant->id,
            'visit_date' => '2026-12-03',
            'title' => 'Tesis Ziyareti',
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 10:00:00', 'Europe/Istanbul'));

        $this->actingAs($consultant)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('visitPlanningReminder.visible', false));
    }
}
