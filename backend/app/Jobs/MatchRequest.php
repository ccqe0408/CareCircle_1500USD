<?php

namespace App\Jobs;

use App\Services\CareService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MatchRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $requestId) {}

    public function handle(CareService $service): void
    {
        $service->match($this->requestId);
    }
}
