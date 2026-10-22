<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\FamilyAuthorization;
use App\Models\HelpRequest;
use App\Models\InboxMessage;
use App\Models\MatchOffer;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Models\Zone;
use App\Services\CareService;
use App\Services\RequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CareController extends Controller
{
    public function __construct(private CareService $care, private RequestPresenter $presenter) {}

    public function meta()
    {
        return ['zones' => Zone::where('active', true)->get(['id', 'name']), 'categories' => ServiceCategory::where('active', true)->get(['id', 'name', 'description']), 'timezone' => 'Asia/Kuala_Lumpur', 'notice' => 'Non-emergency community help. Demo identities are fictional.'];
    }

    public function beneficiaries(Request $r)
    {
        return Beneficiary::where('user_id', $r->user()->id)->orWhere('created_by', $r->user()->id)->orWhereHas('authorizations', fn ($q) => $q->where('user_id', $r->user()->id))->with(['zone', 'authorizations' => fn ($q) => $q->where('user_id', $r->user()->id)])->get()->map(function ($b) use ($r) {
            $allowed = $this->care->authorized($r->user(), $b);

            return ['id' => $b->id, 'name' => $b->name, 'user_id' => $b->user_id, 'zone_id' => $b->zone_id, 'zone' => $b->zone->only(['id', 'name']), 'address' => $allowed ? $b->address : 'Private — family authorization required', 'phone' => $allowed ? $b->phone : '', 'can_request' => $allowed];
        });
    }

    public function createBeneficiary(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'address' => 'required|string|max:500', 'zone_id' => 'required|exists:zones,id', 'kind' => 'required|in:self,family', 'relationship' => 'required_if:kind,family|nullable|string|max:100', 'consent_note' => 'required_if:kind,family|nullable|string|min:10|max:1000']);
        $this->care->check(Zone::findOrFail($v['zone_id'])->active, 'Choose an active service area.');
        if ($v['kind'] === 'self') {
            $this->care->check(! Beneficiary::where('user_id', $r->user()->id)->exists(), 'Your resident profile already exists. Edit it instead.');
        }
        $b = DB::transaction(function () use ($r, $v) {
            $b = Beneficiary::create([...array_intersect_key($v, array_flip(['name', 'phone', 'address', 'zone_id'])), 'user_id' => $v['kind'] === 'self' ? $r->user()->id : null, 'created_by' => $r->user()->id]);
            if ($v['kind'] === 'family') {
                FamilyAuthorization::create(['beneficiary_id' => $b->id, 'user_id' => $r->user()->id, 'relationship' => $v['relationship'], 'consent_note' => $v['consent_note']]);
            }

            return $b;
        });

        return response()->json($b->toArray() + ['can_request' => $v['kind'] === 'self'], 201);
    }

    public function updateBeneficiary(Request $r, Beneficiary $beneficiary)
    {
        abort_unless($beneficiary->user_id === $r->user()->id, 403);
        $v = $r->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'address' => 'required|string|max:500', 'zone_id' => 'required|exists:zones,id']);
        $this->care->check(Zone::findOrFail($v['zone_id'])->active, 'Choose an active service area.');
        $beneficiary->update($v);

        return $beneficiary;
    }

    public function authorizations(Request $r)
    {
        return FamilyAuthorization::where('user_id', $r->user()->id)->orWhereHas('beneficiary', fn ($q) => $q->where('user_id', $r->user()->id))->with(['beneficiary', 'user'])->get()->map(fn ($g) => $g->only(['id', 'beneficiary_id', 'user_id', 'relationship', 'status', 'verified_at']) + ['family_member_name' => $g->user->name, 'beneficiary' => ['id' => $g->beneficiary->id, 'name' => $g->beneficiary->name]]);
    }

    public function inviteFamily(Request $r)
    {
        $v = $r->validate(['beneficiary_id' => 'required|exists:beneficiaries,id', 'email' => 'required|email', 'relationship' => 'required|string|max:100', 'consent_note' => 'required|string|min:10|max:1000']);
        $b = Beneficiary::findOrFail($v['beneficiary_id']);
        abort_unless($b->user_id === $r->user()->id, 403);
        $u = User::where('email', $v['email'])->first();
        $this->care->check($u !== null && $u->id !== $r->user()->id, 'A different registered family member is required.');
        $this->care->check(! FamilyAuthorization::where('beneficiary_id', $b->id)->where('user_id', $u->id)->exists(), 'This family relationship already exists. Contact the community team.');

        return response()->json(FamilyAuthorization::create(['beneficiary_id' => $b->id, 'user_id' => $u->id, 'relationship' => $v['relationship'], 'consent_note' => $v['consent_note']]), 201);
    }

    public function revokeFamily(Request $r, FamilyAuthorization $authorization)
    {
        abort_unless($authorization->beneficiary->user_id === $r->user()->id || $authorization->user_id === $r->user()->id, 403);
        DB::transaction(function () use ($r, $authorization) {
            $authorization->update(['status' => 'revoked']);
            $this->care->audit($r->user(), 'family_self_revoked', $authorization);
            foreach (HelpRequest::where('beneficiary_id', $authorization->beneficiary_id)->where('requester_id', $authorization->user_id)->whereNotIn('status', ['completed', 'cancelled', 'closed'])->get() as $task) {
                $task = HelpRequest::lockForUpdate()->findOrFail($task->id);
                $task->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
                $task->update(['status' => 'escalated', 'resolution_reason' => 'Family consent revoked; administrator review required', 'handover_hash' => null, 'handover_expires_at' => null]);
                $this->care->event($task, $r->user(), 'consent_revoked');
            }
        });

        return ['message' => 'Authorization revoked'];
    }

    public function volunteer(Request $r)
    {
        return ['profile' => $r->user()->volunteerProfile];
    }

    public function applyVolunteer(Request $r)
    {
        $v = $r->validate(['zone_ids' => 'required|array|min:1', 'zone_ids.*' => 'integer|exists:zones,id', 'category_ids' => 'required|array|min:1', 'category_ids.*' => 'integer|exists:service_categories,id', 'max_tasks' => 'required|integer|min:1|max:3', 'availability' => 'required|array|min:1|max:21', 'availability.*.day' => 'required|integer|min:1|max:7', 'availability.*.start' => 'required|date_format:H:i', 'availability.*.end' => 'required|date_format:H:i']);
        foreach ($v['availability'] as $w) {
            $this->care->check($w['end'] > $w['start'], 'Each availability window must end after it starts.');
        }
        $p = $r->user()->volunteerProfile;
        $this->care->check(! $p || $p->status !== 'suspended', 'A suspended volunteer must contact the administrator.');
        $this->care->check(! HelpRequest::where('volunteer_id', $r->user()->id)->whereIn('status', [...CareService::ACTIVE, 'escalated'])->exists(), 'Finish or resolve active tasks before changing availability.');
        $p = VolunteerProfile::updateOrCreate(['user_id' => $r->user()->id], [...$v, 'status' => 'pending', 'verified_by' => null, 'verified_at' => null]);

        return ['profile' => $p, 'message' => 'Application sent for community review'];
    }

    public function index(Request $r)
    {
        $ids = Beneficiary::where('user_id', $r->user()->id)->orWhereHas('authorizations', fn ($q) => $q->where('user_id', $r->user()->id)->where('status', 'approved'))->pluck('id');
        $q = HelpRequest::where(function ($q) use ($r, $ids) {
            $q->whereIn('beneficiary_id', $ids)->orWhere('volunteer_id', $r->user()->id);
        });
        if ($r->query('mode') === 'volunteer') {
            $q = HelpRequest::where('volunteer_id', $r->user()->id);
        }
        if ($r->query('mode') === 'resident') {
            $q = HelpRequest::whereIn('beneficiary_id', $ids);
        }

        return $q->latest()->paginate(30)->through(fn ($task) => $this->presenter->summary($task));
    }

    public function store(Request $r)
    {
        $v = $r->validate(['beneficiary_id' => 'required|exists:beneficiaries,id', 'service_category_id' => 'required|exists:service_categories,id', 'title' => 'required|string|min:5|max:120', 'notes' => 'nullable|string|max:1000', 'pickup_address' => 'required|string|min:5|max:500', 'scheduled_start' => 'required|date', 'scheduled_end' => 'required|date', 'prepaid_confirmed' => 'accepted']);
        $task = $this->care->createRequest($r->user(), $v);

        return response()->json($this->presenter->detail($task, $r->user()), 201);
    }

    public function show(Request $r, HelpRequest $helpRequest)
    {
        return $this->presenter->detail($helpRequest, $r->user());
    }

    public function cancel(Request $r, HelpRequest $helpRequest)
    {
        $v = $r->validate(['reason' => 'required|string|min:5|max:1000']);
        $task = $this->care->cancel($r->user(), $helpRequest, $v['reason']);

        return $this->presenter->summary($task);
    }

    public function offers(Request $r)
    {
        return MatchOffer::where('volunteer_id', $r->user()->id)->where('status', 'pending')->where('expires_at', '>', now())->whereHas('helpRequest', fn ($q) => $q->where('status', 'offered'))->latest()->get()->map(fn ($o) => ['id' => $o->id, 'expires_at' => $o->expires_at->toIso8601String(), 'request' => $this->presenter->summary($o->helpRequest)]);
    }

    public function accept(Request $r, MatchOffer $offer)
    {
        return $this->presenter->detail($this->care->accept($r->user(), $offer->id), $r->user());
    }

    public function decline(Request $r, MatchOffer $offer)
    {
        $this->care->decline($r->user(), $offer->id);

        return ['message' => 'Invitation declined'];
    }

    public function start(Request $r, HelpRequest $helpRequest)
    {
        return $this->presenter->detail($this->care->progress($r->user(), $helpRequest, 'start'), $r->user());
    }

    public function arrive(Request $r, HelpRequest $helpRequest)
    {
        return $this->presenter->detail($this->care->progress($r->user(), $helpRequest, 'arrive'), $r->user());
    }

    public function code(Request $r, HelpRequest $helpRequest)
    {
        return $this->care->issueCode($r->user(), $helpRequest);
    }

    public function confirm(Request $r, HelpRequest $helpRequest)
    {
        $v = $r->validate(['code' => 'required|digits:6']);

        return $this->presenter->detail($this->care->confirm($r->user(), $helpRequest, $v['code']), $r->user());
    }

    public function report(Request $r, HelpRequest $helpRequest)
    {
        $v = $r->validate(['description' => 'required|string|min:10|max:2000']);

        return response()->json($this->care->report($r->user(), $helpRequest, $v['description']), 201);
    }

    public function inbox(Request $r)
    {
        return InboxMessage::where('user_id', $r->user()->id)->latest()->paginate(50);
    }

    public function read(Request $r, InboxMessage $message)
    {
        abort_unless($message->user_id === $r->user()->id, 403);
        $message->update(['read_at' => now()]);

        return ['message' => 'Marked read'];
    }
}
