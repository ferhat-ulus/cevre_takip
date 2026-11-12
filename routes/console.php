<?php

use Illuminate\Support\Facades\Schedule;

// Her gün gece 02:00'da otomatik yedek al
Schedule::command('backup:clean')->daily()->at('01:30');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('backup:monitor')->daily()->at('02:30');
