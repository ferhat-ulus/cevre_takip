<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('waste:transfer-verified-facilities', function () {
    $result = app(\App\Http\Controllers\Api\WasteTransportController::class)->transferVerifiedFacilityCards();
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));
})->purpose('Doğrulanmış tesis eşleşmelerini kalıcı kartlara aktarır.');

// Her gün veritabanı yedeği, pazar günleri ayrıca tam uygulama yedeği al.
Schedule::command('backup:clean')->daily()->at('01:30');
Schedule::command('backup:run --only-db')->daily()->at('02:00');
Schedule::command('backup:run')->weeklyOn(0, '02:15');
Schedule::command('backup:monitor')->daily()->at('02:30');
