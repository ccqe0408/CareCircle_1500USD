<?php

use App\Services\CareService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('carecircle:sweep', function (CareService $service) {
    $this->info(json_encode($service->sweep()));
})->purpose('Expire invitations, rematch and escalate overdue tasks');
Schedule::command('carecircle:sweep')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
