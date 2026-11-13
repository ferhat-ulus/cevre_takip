<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_enabled_administrator_is_available_for_consultant_assignment(): void
    {
        $enabledAdmin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
            'can_be_consultant' => true,
        ]);
        $systemOnlyAdmin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
            'can_be_consultant' => false,
        ]);
        $consultant = User::factory()->create([
            'role' => 'employee',
            'active' => true,
        ]);

        $ids = User::activeConsultants()->pluck('id')->all();

        $this->assertContains($enabledAdmin->id, $ids);
        $this->assertContains($consultant->id, $ids);
        $this->assertNotContains($systemOnlyAdmin->id, $ids);
    }
}
