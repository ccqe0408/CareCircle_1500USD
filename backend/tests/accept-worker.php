<?php

use App\Models\User;
use App\Services\CareService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'carecircle_test') {
    fwrite(STDERR, 'Refusing non-test database');
    exit(2);
}
$start = (float) $argv[3];
while (microtime(true) < $start) {
    usleep(1000);
}
try {
    app(CareService::class)->accept(User::findOrFail((int) $argv[1]), (int) $argv[2]);
    echo 'accepted';
} catch (ValidationException $e) {
    echo 'rejected';
}
