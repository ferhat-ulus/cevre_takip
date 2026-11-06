<?php

namespace Tests\Unit;

use App\Services\ComplianceEngine;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComplianceEngineCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_obligations_are_loaded_once_per_request(): void
    {
        ComplianceEngine::clearObligationsCache();
        $customObligationQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$customObligationQueries) {
            if (str_contains($query->sql, 'configs') && in_array('custom_obligations', $query->bindings, true)) {
                $customObligationQueries++;
            }
        });

        ComplianceEngine::getAll();
        ComplianceEngine::getAll();

        $this->assertSame(1, $customObligationQueries);
    }

    public function test_obligation_cache_can_be_invalidated_after_settings_change(): void
    {
        ComplianceEngine::getAll();
        DB::table('configs')->insert([
            'k' => 'custom_obligations',
            'v' => json_encode([[
                'k' => 'custom-check',
                'n' => 'Özel Kontrol',
                's' => 'Özel Kontrol',
                'g' => 'Özel',
                'kind' => 'periodic',
                'p' => 365,
                'red' => 30,
                'amber' => 60,
            ]]),
        ]);

        ComplianceEngine::clearObligationsCache();

        $this->assertSame('Özel Kontrol', ComplianceEngine::obl('custom-check')['n']);
    }
}
