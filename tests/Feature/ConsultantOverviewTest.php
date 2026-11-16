<?php

namespace Tests\Feature;

use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsultantOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultant_overview_uses_the_account_assignment_not_the_legacy_json_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consultant = User::factory()->create(['role' => 'employee', 'name' => 'Danışman Hesabı']);

        Firm::create([
            'id' => 'assigned-through-account',
            'consultant_user_id' => $consultant->id,
            'data' => ['name' => 'Hesapla Atanmış Tesis', 'status' => 'aktif'],
        ]);
        Firm::create([
            'id' => 'genuinely-unassigned',
            'data' => ['name' => 'Gerçekten Atanmamış Tesis', 'status' => 'aktif'],
        ]);

        $this->actingAs($admin)
            ->withSession(['active_module' => 'environment'])
            ->get(route('consultants.overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.unassignedFirms', 1)
                ->where('consultants', fn ($rows) => collect($rows)->contains(fn (array $row) => $row['id'] === $consultant->id
                    && $row['firmCount'] === 1))
                ->where('unassignedFirms.0.id', 'genuinely-unassigned'));
    }
}
