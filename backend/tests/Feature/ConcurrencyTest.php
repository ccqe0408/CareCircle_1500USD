<?php

namespace Tests\Feature;

use App\Models\Beneficiary;
use App\Models\HelpRequest;
use App\Models\RequestEvent;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Models\Zone;
use App\Services\CareService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_serializes_competing_accepts_and_capacity_reservations(): void
    {
        Queue::fake();
        $zone = Zone::create(['name' => 'Concurrent test']);
        $category = ServiceCategory::create(['name' => 'Parcel', 'slug' => 'ordinary-parcel', 'description' => 'Test']);
        $resident = User::create(['name' => 'Concurrent resident', 'email' => 'concurrentresident@example.test', 'password' => 'Password12345']);
        $v = User::create(['name' => 'Concurrent volunteer', 'email' => 'concurrentvolunteer@example.test', 'password' => 'Password12345']);
        $b = Beneficiary::create(['user_id' => $resident->id, 'created_by' => $resident->id, 'name' => 'Recipient', 'phone' => '000', 'address' => 'Fictional address', 'zone_id' => $zone->id]);
        VolunteerProfile::create(['user_id' => $v->id, 'status' => 'approved', 'zone_ids' => [$zone->id], 'category_ids' => [$category->id], 'availability' => array_map(fn ($d) => ['day' => $d, 'start' => '00:00', 'end' => '23:59'], range(1, 7)), 'max_tasks' => 1]);
        $care = app(CareService::class);
        $requests = [];
        $offers = [];
        for ($i = 0; $i < 2; $i++) {
            $r = $care->createRequest($resident, ['beneficiary_id' => $b->id, 'service_category_id' => $category->id, 'title' => 'Concurrent request '.$i, 'pickup_address' => 'Fictional collection counter', 'scheduled_start' => now()->addDay()->setTime(8, 0)->toIso8601String(), 'scheduled_end' => now()->addDay()->setTime(9, 0)->toIso8601String()]);
            $care->match($r->id);
            $requests[] = $r;
            $offers[] = $r->offers()->first()->id;
        }
        $results = $this->race($v->id, $offers);
        sort($results);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertSame(1, HelpRequest::where('volunteer_id', $v->id)->where('status', 'accepted')->count());
        $accepted = HelpRequest::where('status', 'accepted')->firstOrFail();
        $winningOffer = $accepted->offers()->where('status', 'accepted')->first()->id;
        $this->assertSame(['rejected', 'rejected'], $this->race($v->id, [$winningOffer, $winningOffer]));
        $this->assertSame(1, RequestEvent::where('action', 'accepted')->count());
    }

    private function race(int $user, array $offers): array
    {
        $jobs = [];
        $start = microtime(true) + 1;
        foreach ($offers as $offer) {
            $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'carecircle_test', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => '']);
            $p = proc_open([PHP_BINARY, base_path('tests/accept-worker.php'), (string) $user, (string) $offer, (string) $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $jobs[] = [$p, $pipes];
        }
        $results = [];
        foreach ($jobs as [$p,$pipes]) {
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($p);
            $this->assertSame(0, $code, $err);
            $results[] = trim($out);
        }

return $results;
    }
}
