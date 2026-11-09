<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class InitialAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'initial-admin.username' => 'clean-admin',
            'initial-admin.name' => 'Temiz Kurulum Yöneticisi',
            'initial-admin.email' => 'admin@example.test',
            'initial-admin.password' => 'a-strong-initial-password',
        ]);
    }

    public function test_database_seeder_creates_only_the_configured_admin_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'username' => 'clean-admin',
            'name' => 'Temiz Kurulum Yöneticisi',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'active' => true,
            'must_change' => false,
        ]);
        $this->assertTrue(Hash::check('a-strong-initial-password', User::firstOrFail()->password));
        $this->assertDatabaseCount('firms', 0);
        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('configs', 0);
        $this->assertDatabaseCount('logs', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('firm_logs', 0);
    }

    public function test_seeding_again_does_not_reset_an_existing_admin_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::firstOrFail()->update(['password' => 'a-different-strong-password']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('a-different-strong-password', User::firstOrFail()->password));
    }

    public function test_seeder_requires_a_strong_initial_admin_password(): void
    {
        config(['initial-admin.password' => 'short']);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Seeder should reject a short initial password.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('en az 16 karakter', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeder_does_not_promote_an_existing_non_admin_account(): void
    {
        User::factory()->create(['username' => 'clean-admin', 'role' => 'employee']);

        $this->expectException(\LogicException::class);
        $this->seed(DatabaseSeeder::class);
    }
}
