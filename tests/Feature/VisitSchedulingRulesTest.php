<?php

namespace Tests\Feature;

use App\Models\Firm;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitSchedulingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_visits_can_only_be_created_for_tomorrow_or_later(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:30:00', 'UTC'));
        [$user, $firm] = $this->createConsultantAndFirm();

        $this->actingAs($user)
            ->postJson('/api/visits', [
                'firm_id' => $firm->id,
                'visit_date' => '2026-11-05',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'error' => 'Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.',
            ]);

        $this->actingAs($user)
            ->postJson('/api/visits', [
                'firm_id' => $firm->id,
                'visit_date' => '2026-11-06',
            ])
            ->assertOk();
    }

    public function test_past_visits_cannot_be_deleted(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:30:00', 'UTC'));
        [$user, $firm] = $this->createConsultantAndFirm();
        $visit = $this->createVisit($user, $firm, '2026-11-04');

        $this->actingAs($user)
            ->deleteJson("/api/visits/{$visit->id}")
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'Geçmiş tarihli ziyaretler silinemez.']);

        $this->assertDatabaseHas('visits', ['id' => $visit->id]);
    }

    public function test_today_or_future_visits_can_be_deleted_before_16(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 12:59:00', 'UTC'));
        [$user, $firm] = $this->createConsultantAndFirm();
        $visit = $this->createVisit($user, $firm, '2026-11-05');

        $this->actingAs($user)
            ->deleteJson("/api/visits/{$visit->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
    }

    public function test_today_visit_deletion_is_blocked_at_and_after_16(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 13:54:00', 'UTC'));
        [$user, $firm] = $this->createConsultantAndFirm();
        $visit = $this->createVisit($user, $firm, '2026-11-05');

        $this->actingAs($user)
            ->deleteJson("/api/visits/{$visit->id}")
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'Bugün tarihli ziyaretler saat 16.00’dan sonra silinemez.']);

        $this->assertDatabaseHas('visits', ['id' => $visit->id]);
    }

    public function test_future_visit_can_be_deleted_after_16(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 13:00:00', 'UTC'));
        [$user, $firm] = $this->createConsultantAndFirm();
        $visit = $this->createVisit($user, $firm, '2026-11-06');

        $this->actingAs($user)
            ->deleteJson("/api/visits/{$visit->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
    }

    private function createConsultantAndFirm(): array
    {
        $user = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        $firm = Firm::create([
            'id' => 'visit-rules-firm',
            'data' => ['name' => 'Ziyaret Test Tesisi', 'consultant' => $user->name],
        ]);

        return [$user, $firm];
    }

    private function createVisit(User $user, Firm $firm, string $date): Visit
    {
        return Visit::create([
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'visit_date' => $date,
            'title' => 'Tesis Ziyareti',
        ]);
    }
}
