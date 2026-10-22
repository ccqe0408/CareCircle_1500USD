<?php

use App\Models\HelpRequest;
use App\Models\User;
use App\Services\CareService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.connections.mysql.database') !== 'carecircle') {
    fwrite(STDERR, 'Local demo environment required');
    exit(2);
}
$admin = User::where('email', 'admin@carecircle.test')->where('is_admin', true)->firstOrFail();
$query = HelpRequest::whereNotIn('status', ['completed', 'closed', 'cancelled'])->where(function ($q) {
    $q->where('notes', 'like', 'AUTOMATED_DEMO:%');
});
// One explicitly identified task from the pre-marker automated run; this never deletes records.
if (in_array('--legacy-run-7', $argv, true)) {
    $query = HelpRequest::where('id', 7)->where('title', 'like', 'Groceries for Mrs Tan%')->whereNotIn('status', ['completed', 'closed', 'cancelled']);
}
$count = 0;
foreach ($query->get() as $request) {
    app(CareService::class)->close($admin, $request, 'Aborted fictional automated demo run; no real items were collected.');
    $count++;
}
echo "Closed $count aborted fictional demo tasks with audit records.\n";
