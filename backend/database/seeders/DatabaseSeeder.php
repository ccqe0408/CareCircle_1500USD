<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\FamilyAuthorization;
use App\Models\HelpRequest;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Models\Zone;
use App\Services\CareService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::firstOrCreate(['name' => 'Maple Grove']);
        Zone::firstOrCreate(['name' => 'Lakeside']);
        $grocery = ServiceCategory::firstOrCreate(['slug' => 'prepaid-groceries'], ['name' => 'Prepaid grocery collection', 'description' => 'Collect an already-paid grocery order. No cash handling, medicines or entry into homes.']);
        $parcel = ServiceCategory::firstOrCreate(['slug' => 'ordinary-parcel'], ['name' => 'Ordinary parcel collection', 'description' => 'Collect a non-valuable parcel from the community office. No restricted or heavy items.']);
        Setting::firstOrCreate(['key' => 'offer_minutes'], ['value' => '15']);
        $users = [];
        foreach (['admin' => 'Community Admin', 'resident' => 'Mrs Tan', 'family' => 'Lily Tan', 'ali' => 'Ali Rahman', 'mei' => 'Mei Wong', 'dan' => 'Daniel Lee', 'sofia' => 'Sofia Chen', 'jason' => 'Jason Lim'] as $key => $name) {
            $users[$key] = User::firstOrCreate(['email' => $key.'@carecircle.test'], ['name' => $name, 'phone' => '010-000-0000', 'password' => $key === 'admin' ? 'CircleAdmin2026!' : 'CircleDemo2026!']);
        }
        $users['admin']->forceFill(['is_admin' => true])->save();
        $b = Beneficiary::firstOrCreate(['user_id' => $users['resident']->id], ['created_by' => $users['resident']->id, 'name' => 'Mrs Tan (demo)', 'phone' => '010-000-0000', 'address' => 'DEMO ONLY · 12 Maple Lane, Maple Grove', 'zone_id' => $zone->id]);
        FamilyAuthorization::firstOrCreate(['beneficiary_id' => $b->id, 'user_id' => $users['family']->id], ['relationship' => 'Daughter', 'consent_note' => 'Fictional demo: recipient consent reviewed in person by the community team.', 'status' => 'approved', 'verified_by' => $users['admin']->id, 'verified_at' => now()]);
        $other = Beneficiary::firstOrCreate(['name' => 'Mr Lim (demo)'], ['created_by' => $users['jason']->id, 'phone' => '010-000-0000', 'address' => 'DEMO ONLY · 8 Maple Walk', 'zone_id' => $zone->id]);
        FamilyAuthorization::firstOrCreate(['beneficiary_id' => $other->id, 'user_id' => $users['jason']->id], ['relationship' => 'Son', 'consent_note' => 'Fictional demo: waiting for consent verification by community staff.']);
        foreach (['ali', 'mei', 'dan', 'sofia'] as $key) {
            $availability = [];
            for ($day = 1; $day <= 7; $day++) {
                $availability[] = ['day' => $day, 'start' => $key === 'dan' ? '09:00' : '08:00', 'end' => $key === 'dan' ? '12:00' : '21:00'];
            }
            VolunteerProfile::firstOrCreate(['user_id' => $users[$key]->id], ['status' => $key === 'sofia' ? 'pending' : 'approved', 'zone_ids' => [$zone->id], 'category_ids' => [$grocery->id, $parcel->id], 'availability' => $availability, 'max_tasks' => 1, 'verification_note' => $key === 'sofia' ? null : 'Fictional demo: identity and service area checked.', 'verified_by' => $key === 'sofia' ? null : $users['admin']->id, 'verified_at' => $key === 'sofia' ? null : now()]);
        }
        if (! HelpRequest::where('title', 'Collect prepaid groceries (demo)')->exists()) {
            $start = now('Asia/Kuala_Lumpur')->addDay()->setTime(16, 0)->utc();
            $r = app(CareService::class)->createRequest($users['family'], ['beneficiary_id' => $b->id, 'service_category_id' => $grocery->id, 'title' => 'Collect prepaid groceries (demo)', 'notes' => 'Fictional demonstration. Rice, bread and tissues. Order already paid. Meet outside the home.', 'pickup_address' => 'DEMO ONLY · Maple Market collection counter', 'scheduled_start' => $start->toIso8601String(), 'scheduled_end' => $start->copy()->addHour()->toIso8601String()]);
            app(CareService::class)->match($r->id);
        }
    }
}
