<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Firm;
use App\Models\FirmLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsultantHandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_transfer_firms_and_deactivate_a_consultant_with_audited_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Yönetici']);
        $departingConsultant = User::factory()->create(['role' => 'employee', 'name' => 'AYRILAN DANIŞMAN']);
        $replacement = User::factory()->create(['role' => 'employee', 'name' => 'YENİ DANIŞMAN']);

        foreach (['handover-a', 'handover-b'] as $id) {
            Firm::create([
                'id' => $id,
                'data' => ['name' => $id, 'consultant' => $departingConsultant->name],
            ]);
        }

        $this->actingAs($admin)
            ->postJson("/api/users/{$departingConsultant->id}/consultant-handover", [
                'replacement_user_id' => $replacement->id,
                'reason' => 'Görev değişikliği',
                'deactivate' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'transferred' => 2]);

        $this->assertFalse($departingConsultant->fresh()->active);
        $this->assertSame(2, Firm::query()->where('data->consultant', $replacement->name)->count());
        $this->assertSame(2, FirmLog::where('action', 'consultant_changed')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'consultant_handover',
            'description' => 'AYRILAN DANIŞMAN danışmanının 2 tesisi YENİ DANIŞMAN danışmanına devredildi. Gerekçe: Görev değişikliği Danışman hesabı pasife alındı.',
        ]);
    }

    public function test_admin_can_transfer_a_subset_of_a_consultants_firms(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Yönetici']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        $replacement = User::factory()->create(['role' => 'employee', 'name' => 'YENİ DANIŞMAN']);
        foreach (['partial-a' => 'Devredilen Tesis', 'partial-b' => 'Kalan Tesis'] as $id => $name) {
            Firm::create([
                'id' => $id,
                'data' => ['name' => $name, 'consultant' => $consultant->name],
            ]);
        }

        $this->actingAs($admin)
            ->postJson("/api/users/{$consultant->id}/consultant-handover", [
                'replacement_user_id' => $replacement->id,
                'reason' => 'Portföy bölündü',
                'firm_ids' => ['partial-a'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'transferred' => 1]);

        $this->assertSame($replacement->name, Firm::find('partial-a')->data['consultant']);
        $this->assertSame($consultant->name, Firm::find('partial-b')->data['consultant']);
        $this->assertTrue($consultant->fresh()->active);
        $this->assertDatabaseHas('firm_logs', [
            'firm_id' => 'partial-a',
            'action' => 'consultant_changed',
            'from_consultant' => $consultant->name,
            'to_consultant' => $replacement->name,
        ]);
        $this->assertDatabaseMissing('firm_logs', [
            'firm_id' => 'partial-b',
            'action' => 'consultant_changed',
        ]);
    }

    public function test_partial_handover_cannot_deactivate_the_consultant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        $replacement = User::factory()->create(['role' => 'employee', 'name' => 'YENİ DANIŞMAN']);
        foreach (['stay-a', 'stay-b'] as $id) {
            Firm::create([
                'id' => $id,
                'data' => ['name' => $id, 'consultant' => $consultant->name],
            ]);
        }

        $this->actingAs($admin)
            ->postJson("/api/users/{$consultant->id}/consultant-handover", [
                'replacement_user_id' => $replacement->id,
                'reason' => 'Eksik devir',
                'deactivate' => true,
                'firm_ids' => ['stay-a'],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'error' => 'Danışmanı pasife almak için bütün tesislerin devredilmesi gerekir.',
            ]);

        $this->assertTrue($consultant->fresh()->active);
        $this->assertSame($consultant->name, Firm::find('stay-a')->data['consultant']);
    }

    public function test_consultant_with_assigned_firms_cannot_be_deactivated_without_a_handover(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        Firm::create([
            'id' => 'blocked-handover',
            'data' => ['name' => 'Atanmış Tesis', 'consultant' => $consultant->name],
        ]);

        $this->actingAs($admin)
            ->putJson("/api/users/{$consultant->id}", ['active' => false])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'error' => 'Bu danışmana atanmış tesisler var. Önce tesisleri devir işlemiyle başka bir danışmana aktarın.',
            ]);

        $this->assertTrue($consultant->fresh()->active);
    }

    public function test_only_admins_can_transfer_consultant_firms(): void
    {
        $consultant = User::factory()->create(['role' => 'employee']);

        $this->actingAs($consultant)
            ->postJson("/api/users/{$consultant->id}/consultant-handover", [
                'reason' => 'Görev değişikliği',
                'replacement_user_id' => null,
            ])
            ->assertForbidden();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_can_see_consultant_assignment_counts_and_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'DANIŞMAN']);
        Firm::create([
            'id' => 'history-firm',
            'data' => ['name' => 'Geçmiş Tesisi', 'consultant' => $consultant->name],
        ]);
        FirmLog::create([
            'firm_id' => 'history-firm',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => "Danışman değiştirildi: 'Eski' yerine '{$consultant->name}' atandı.",
        ]);

        $this->actingAs($admin)
            ->get('/kullanicilar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("firmCounts.{$consultant->name}", 1)
                ->has('consultantHistory', 1)
                ->where('consultantHistory.0.description', "Geçmiş Tesisi: Danışman değiştirildi: 'Eski' yerine 'DANIŞMAN' atandı.")
            );
    }

    public function test_admin_can_view_a_consultants_firms_and_previous_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $activeConsultant = User::factory()->create(['role' => 'employee', 'name' => 'MEVCUT DANIŞMAN']);
        $inactiveConsultant = User::factory()->create([
            'role' => 'employee',
            'name' => 'AYRILAN DANIŞMAN',
            'active' => false,
        ]);
        Firm::create([
            'id' => 'overview-firm',
            'data' => ['name' => 'Danışman Takip Tesisi', 'consultant' => $activeConsultant->name],
        ]);
        FirmLog::create([
            'firm_id' => 'overview-firm',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => "Danışman devri: '{$inactiveConsultant->name}' yerine '{$activeConsultant->name}' atandı. Gerekçe: Organizasyon değişikliği",
            'from_consultant' => $inactiveConsultant->name,
            'to_consultant' => $activeConsultant->name,
            'reason' => 'Organizasyon değişikliği',
            'history_date' => '2024-11',
        ]);

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.activeConsultants', 1)
                ->where('summary.inactiveConsultants', 1)
                ->where('consultants.0.name', 'AYRILAN DANIŞMAN')
                ->where('consultants.1.firms.0.name', 'Danışman Takip Tesisi')
                ->where('consultants.1.firms.0.previousConsultant', 'AYRILAN DANIŞMAN')
                ->where('consultants.1.firms.0.history.0.reason', 'Organizasyon değişikliği')
                ->where('consultants.1.firms.0.history.0.history_date', '2024-11')
                ->where('history.0.firm_name', 'Danışman Takip Tesisi')
                ->where('history.0.history_date', '2024-11')
            );
    }

    public function test_consultant_portfolio_shows_when_each_firm_was_received_and_relinquished(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $formerConsultant = User::factory()->create(['role' => 'employee', 'name' => 'ALFA DANIŞMAN']);
        $middleConsultant = User::factory()->create(['role' => 'employee', 'name' => 'BRAVO DANIŞMAN']);
        $currentConsultant = User::factory()->create(['role' => 'employee', 'name' => 'CHARLIE DANIŞMAN']);

        Firm::create([
            'id' => 'timeline-firm',
            'data' => ['name' => 'Zaman Çizelgesi Tesisi', 'consultant' => $currentConsultant->name],
        ]);

        foreach ([
            ['from' => $formerConsultant->name, 'to' => $middleConsultant->name, 'date' => '2024-01'],
            ['from' => $middleConsultant->name, 'to' => $currentConsultant->name, 'date' => '2024-06'],
        ] as $transfer) {
            FirmLog::create([
                'firm_id' => 'timeline-firm',
                'user_id' => $admin->id,
                'action' => 'consultant_changed',
                'description' => 'Danışman geçmişi aktarıldı.',
                'from_consultant' => $transfer['from'],
                'to_consultant' => $transfer['to'],
                'history_date' => $transfer['date'],
            ]);
        }

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultants.0.name', $formerConsultant->name)
                ->where('consultants.0.assignmentHistory.0.firm_name', 'Zaman Çizelgesi Tesisi')
                ->where('consultants.0.assignmentHistory.0.received_at', null)
                ->where('consultants.0.assignmentHistory.0.left_at', '2024-01')
                ->where('consultants.1.name', $middleConsultant->name)
                ->where('consultants.1.assignmentHistory.0.received_at', '2024-01')
                ->where('consultants.1.assignmentHistory.0.left_at', '2024-06')
                ->where('consultants.2.name', $currentConsultant->name)
                ->where('consultants.2.assignmentHistory.0.received_at', '2024-06')
                ->where('consultants.2.assignmentHistory.0.left_at', null)
                ->where('consultants.2.assignmentHistory.0.is_current', true)
            );
    }

    public function test_history_only_consultants_remain_searchable_in_the_portfolio_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $currentConsultant = User::factory()->create(['role' => 'employee', 'name' => 'MEVCUT DANIŞMAN']);
        Firm::create([
            'id' => 'former-consultant-firm',
            'data' => ['name' => 'Eski Danışman Tesisi', 'consultant' => $currentConsultant->name],
        ]);
        FirmLog::create([
            'firm_id' => 'former-consultant-firm',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => 'Danışman geçmişi aktarıldı.',
            'from_consultant' => 'SİSTEMDE OLMAYAN ESKİ DANIŞMAN',
            'to_consultant' => $currentConsultant->name,
            'history_date' => '2023-08',
        ]);

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultants.1.name', 'SİSTEMDE OLMAYAN ESKİ DANIŞMAN')
                ->where('consultants.1.historical', true)
                ->where('consultants.1.assignmentHistory.0.firm_name', 'Eski Danışman Tesisi')
                ->where('consultants.1.assignmentHistory.0.left_at', '2023-08')
            );
    }

    public function test_spelling_variants_attach_to_the_active_consultant_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'EZGİ AKAR']);
        $other = User::factory()->create(['role' => 'employee', 'name' => 'DİĞER DANIŞMAN']);
        Firm::create([
            'id' => 'spelling-current',
            'data' => ['name' => 'Güncel Tesis', 'consultant' => 'Ezgi AKAR'],
        ]);
        Firm::create([
            'id' => 'spelling-past',
            'data' => ['name' => 'Bırakılan Tesis', 'consultant' => $other->name],
        ]);
        FirmLog::create([
            'firm_id' => 'spelling-past',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => 'Danışman geçmişi aktarıldı.',
            'from_consultant' => 'Ezgi AKAR',
            'to_consultant' => $other->name,
            'history_date' => '2022-03',
        ]);

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultants', fn ($consultants) => collect($consultants)->where('historical', true)->isEmpty())
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('name', 'EZGİ AKAR')['firmCount'] === 1)
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('name', 'EZGİ AKAR')['aliases'] === ['Ezgi AKAR'])
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('name', 'EZGİ AKAR')['assignmentHistory'][0]['firm_name'] === 'Bırakılan Tesis')
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('name', 'EZGİ AKAR')['pastFirmCount'] === 1)
            );
    }

    public function test_punctuation_variants_collapse_into_one_unmatched_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $current = User::factory()->create(['role' => 'employee', 'name' => 'MEVCUT DANIŞMAN']);
        foreach ([
            ['id' => 'punctuation-a', 'from' => 'Ezgi.Ö. USLU', 'date' => '2021-01'],
            ['id' => 'punctuation-b', 'from' => 'Ezgi Ö.USLU', 'date' => '2021-06'],
        ] as $transfer) {
            Firm::create([
                'id' => $transfer['id'],
                'data' => ['name' => $transfer['id'], 'consultant' => $current->name],
            ]);
            FirmLog::create([
                'firm_id' => $transfer['id'],
                'user_id' => $admin->id,
                'action' => 'consultant_changed',
                'description' => 'Danışman geçmişi aktarıldı.',
                'from_consultant' => $transfer['from'],
                'to_consultant' => $current->name,
                'history_date' => $transfer['date'],
            ]);
        }

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultants', fn ($consultants) => collect($consultants)->where('historical', true)->count() === 1)
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('historical', true)['periodCount'] === 2)
                ->where('consultants', fn ($consultants) => collect($consultants)->firstWhere('historical', true)['pastFirmCount'] === 2)
            );
    }

    public function test_missing_end_date_stays_on_the_ongoing_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'ALPASLAN ÇOBAN']);
        Firm::create([
            'id' => 'ongoing-firm',
            'data' => ['name' => 'Devam Eden Tesis', 'consultant' => $consultant->name],
        ]);
        FirmLog::create([
            'firm_id' => 'ongoing-firm',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => 'Danışman geçmişi aktarıldı.',
            'from_consultant' => 'Beyda ÜNVER',
            'to_consultant' => 'Alpaslan ÇOBAN',
            'reason' => 'CSV_IMPORT: historical consultant record',
            'history_date' => '2024-09',
        ]);
        FirmLog::create([
            'firm_id' => 'ongoing-firm',
            'user_id' => $admin->id,
            'action' => 'consultant_changed',
            'description' => 'Danışman geçmişi aktarıldı.',
            'from_consultant' => 'Alpaslan ÇOBAN',
            'to_consultant' => null,
            'reason' => 'CSV_IMPORT: date missing',
            'history_date' => null,
        ]);

        $this->actingAs($admin)
            ->get('/danisman-takibi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('consultants', function ($consultants) use ($consultant) {
                    $history = collect($consultants)->firstWhere('name', $consultant->name)['assignmentHistory'];

                    return count($history) === 1
                        && $history[0]['firm_name'] === 'Devam Eden Tesis'
                        && $history[0]['received_at'] === '2024-09'
                        && $history[0]['left_at'] === null
                        && $history[0]['is_current'] === true;
                })
            );
    }

    public function test_consultant_tracking_page_is_admin_only(): void
    {
        $consultant = User::factory()->create(['role' => 'employee']);

        $this->actingAs($consultant)
            ->get('/danisman-takibi')
            ->assertForbidden();
    }
}
