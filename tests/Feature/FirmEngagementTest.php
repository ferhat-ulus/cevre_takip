<?php

namespace Tests\Feature;

use App\Models\Firm;
use App\Models\FirmLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FirmEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_contract_and_service_dates_and_end_the_engagement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        Firm::create([
            'id' => 'engagement-firm',
            'data' => [
                'name' => 'Dönem Tesisi',
                'consultant' => $consultant->name,
                'status' => 'aktif',
                'items' => [],
            ],
        ]);

        $this->actingAs($admin)
            ->putJson('/api/firms/engagement-firm/engagement', [
                'contract_date' => '2024-01-15',
                'started_at' => '2024-02-01',
                'ended_at' => '2026-03-31',
            ])
            ->assertOk()
            ->assertJson(['status' => 'sonlandi']);

        $firm = Firm::find('engagement-firm');
        $this->assertSame('2024-01-15', $firm->data['contractDate']);
        $this->assertSame('2024-02-01', $firm->data['serviceStartedAt']);
        $this->assertSame('2026-03-31', $firm->data['serviceEndedAt']);
        $this->assertSame('sonlandi', $firm->data['status']);
        $this->assertDatabaseHas('firm_logs', [
            'firm_id' => 'engagement-firm',
            'action' => 'engagement_ended',
        ]);

        $this->actingAs($admin)
            ->get('/firma/engagement-firm')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firm.contractDate', '2024-01-15')
                ->where('firm.serviceEndedAt', '2026-03-31')
                ->where('canManageEngagement', true)
            );

        $this->actingAs($consultant)
            ->get('/firma/engagement-firm')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firm.serviceStartedAt', '2024-02-01')
                ->where('canManageEngagement', false)
            );
    }

    public function test_consultant_cannot_change_engagement_dates(): void
    {
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        Firm::create([
            'id' => 'protected-firm',
            'data' => [
                'name' => 'Korunan Tesis',
                'consultant' => $consultant->name,
                'status' => 'aktif',
                'contractDate' => '2024-01-01',
                'serviceStartedAt' => '2024-01-01',
            ],
        ]);

        $this->actingAs($consultant)
            ->putJson('/api/firms/protected-firm/engagement', [
                'contract_date' => '2025-01-01',
                'started_at' => '2025-01-01',
                'ended_at' => '2025-06-01',
            ])
            ->assertForbidden();

        $this->actingAs($consultant)
            ->putJson('/api/firms/protected-firm', [
                'sector' => 'TEKSTİL',
                'contractDate' => '2025-01-01',
                'serviceEndedAt' => '2025-06-01',
                'status' => 'sonlandi',
            ])
            ->assertOk();

        $firm = Firm::find('protected-firm');
        $this->assertSame('2024-01-01', $firm->data['contractDate']);
        $this->assertSame('aktif', $firm->data['status']);
        $this->assertArrayNotHasKey('serviceEndedAt', $firm->data);
        $this->assertSame(0, FirmLog::where('action', 'engagement_ended')->count());
    }

    public function test_consultant_cannot_reassign_their_own_firm(): void
    {
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'MEVCUT DANIŞMAN']);
        $replacement = User::factory()->create(['role' => 'employee', 'name' => 'DİĞER DANIŞMAN']);
        Firm::create([
            'id' => 'reassignment-protected-firm',
            'data' => [
                'name' => 'Devir Koruması Tesisi',
                'consultant' => $consultant->name,
                'items' => [],
            ],
        ]);

        $this->actingAs($consultant)
            ->putJson('/api/firms/reassignment-protected-firm', ['consultant' => $replacement->name])
            ->assertForbidden()
            ->assertJsonFragment(['error' => 'Danışman atamasını yalnızca yöneticiler değiştirebilir.']);

        $this->assertSame($consultant->name, Firm::find('reassignment-protected-firm')->data['consultant']);
    }

    public function test_end_date_cannot_be_before_the_start_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'date-order-firm',
            'data' => ['name' => 'Sıra Tesisi', 'status' => 'aktif'],
        ]);

        $this->actingAs($admin)
            ->putJson('/api/firms/date-order-firm/engagement', [
                'started_at' => '2026-05-01',
                'ended_at' => '2026-04-01',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'Çalışma bitişi, çalışmaya başlama tarihinden önce olamaz.']);
    }

    public function test_closed_firm_stays_in_the_facility_list_but_leaves_the_open_work_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Firm::create([
            'id' => 'closed-list-firm',
            'data' => [
                'name' => 'Kapanan Tesis',
                'consultant' => 'DANIŞMAN',
                'status' => 'sonlandi',
                'serviceStartedAt' => '2023-01-01',
                'serviceEndedAt' => '2025-12-31',
                'items' => [],
            ],
        ]);

        $this->actingAs($admin)
            ->get('/tesisler')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firms', fn ($firms) => collect($firms)->contains(fn ($firm) => $firm['id'] === 'closed-list-firm'
                    && $firm['serviceEndedAt'] === '2025-12-31'))
            );

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firmSummary.total', 0)
            );
    }
}
