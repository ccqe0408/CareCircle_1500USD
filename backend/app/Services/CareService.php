<?php

namespace App\Services;

use App\Jobs\MatchRequest;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\FamilyAuthorization;
use App\Models\HelpRequest;
use App\Models\InboxMessage;
use App\Models\Incident;
use App\Models\MatchOffer;
use App\Models\RequestEvent;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CareService
{
    public const ACTIVE = ['accepted', 'in_progress', 'awaiting_confirmation'];

    public function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }

    public function admin(User $actor): void
    {
        abort_unless($actor->is_admin && ! $actor->suspended, 403);
    }

    public function authorized(User $actor, Beneficiary $b): bool
    {
        return ! $actor->suspended && ($actor->is_admin || $b->user_id === $actor->id || FamilyAuthorization::where('beneficiary_id', $b->id)->where('user_id', $actor->id)->where('status', 'approved')->exists());
    }

    public function canView(User $actor, HelpRequest $r): bool
    {
        return ! $actor->suspended && ($actor->is_admin || $this->authorized($actor, $r->beneficiary) || ($r->volunteer_id === $actor->id && $actor->volunteerProfile()->where('status', 'approved')->exists() && in_array($r->status, [...self::ACTIVE, 'completed', 'escalated'])));
    }

    public function event(HelpRequest $r, ?User $actor, string $action, array $details = []): void
    {
        RequestEvent::create(['help_request_id' => $r->id, 'actor_id' => $actor?->id, 'action' => $action, 'details' => $details]);
    }

    public function audit(User $actor, string $action, $record, array $details = []): void
    {
        AuditLog::create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => class_basename($record), 'subject_id' => $record->id, 'details' => $details]);
    }

    public function notify(int $userId, HelpRequest $r, string $title, string $body): void
    {
        InboxMessage::create(['user_id' => $userId, 'help_request_id' => $r->id, 'title' => $title, 'body' => $body]);
    }

    public function createRequest(User $actor, array $data): HelpRequest
    {
        $b = Beneficiary::findOrFail($data['beneficiary_id']);
        abort_unless($this->authorized($actor, $b), 403, 'An approved family authorization is required.');
        $this->check($b->zone->active, 'This service area is not currently available.');
        $category = ServiceCategory::findOrFail($data['service_category_id']);
        $this->check($category->active, 'This category is not currently available.');
        $start = Carbon::parse($data['scheduled_start'])->utc();
        $end = Carbon::parse($data['scheduled_end'])->utc();
        $this->check($start->isFuture() && $end->gt($start) && $start->diffInMinutes($end) <= 240 && $end->lte(now()->addDays(30)), 'Choose a future service window of up to four hours, within 30 days.');

        return DB::transaction(function () use ($actor, $data, $b, $start, $end) {
            $r = HelpRequest::create(['requester_id' => $actor->id, 'beneficiary_id' => $b->id, 'service_category_id' => $data['service_category_id'], 'zone_id' => $b->zone_id, 'title' => $data['title'], 'notes' => $data['notes'] ?? null, 'pickup_address' => $data['pickup_address'], 'delivery_address' => $b->address, 'contact_phone' => $b->phone, 'scheduled_start' => $start, 'scheduled_end' => $end, 'status' => 'matching']);
            $this->event($r, $actor, 'request_created');
            MatchRequest::dispatch($r->id)->afterCommit();

            return $r;
        });
    }

    public function ineligibility(User $u, HelpRequest $r): array
    {
        $u->refresh();
        $u->load('volunteerProfile');
        $p = $u->volunteerProfile;
        $reasons = [];
        if (! $r->zone->active || ! $r->category->active) {
            $reasons[] = 'Service area or category is paused';
        }
        if ($u->suspended || ! $p || $p->status !== 'approved') {
            return ['Volunteer is not approved and active'];
        }
        if ($u->id === $r->requester_id || $u->id === $r->beneficiary->user_id || $this->authorized($u, $r->beneficiary)) {
            $reasons[] = 'Cannot accept your own or an authorized family request';
        }
        if (! in_array($r->zone_id, $p->zone_ids)) {
            $reasons[] = 'Outside service area';
        }
        if (! in_array($r->service_category_id, $p->category_ids)) {
            $reasons[] = 'Category not selected';
        }
        $start = $r->scheduled_start->copy()->timezone('Asia/Kuala_Lumpur');
        $end = $r->scheduled_end->copy()->timezone('Asia/Kuala_Lumpur');
        $available = false;
        foreach ($p->availability as $window) {
            if ((int) ($window['day'] ?? 0) === $start->dayOfWeekIso && $start->toDateString() === $end->toDateString() && ($window['start'] ?? '99:99') <= $start->format('H:i') && ($window['end'] ?? '00:00') >= $end->format('H:i')) {
                $available = true;
            }
        }
        if (! $available) {
            $reasons[] = 'Outside available hours';
        }
        $active = HelpRequest::where('volunteer_id', $u->id)->whereIn('status', [...self::ACTIVE, 'escalated'])->whereKeyNot($r->id);
        if ((clone $active)->count() >= $p->max_tasks) {
            $reasons[] = 'Active task capacity reached';
        }
        if ((clone $active)->where('scheduled_start', '<', $r->scheduled_end)->where('scheduled_end', '>', $r->scheduled_start)->exists()) {
            $reasons[] = 'Time conflict with another task';
        }
        if (! $r->scheduled_start->isFuture()) {
            $reasons[] = 'Service window has started';
        }

        return $reasons;
    }

    public function candidates(HelpRequest $r): array
    {
        return User::whereHas('volunteerProfile')->orderBy('id')->get()->map(function (User $u) use ($r) {
            $reasons = $this->ineligibility($u, $r);

            return ['id' => $u->id, 'name' => $u->name, 'eligible' => empty($reasons), 'reasons' => $reasons];
        })->all();
    }

    public function match(int $requestId): HelpRequest
    {
        return DB::transaction(function () use ($requestId) {
            $r = HelpRequest::lockForUpdate()->findOrFail($requestId);
            if (! in_array($r->status, ['matching', 'offered'])) {
                return $r;
            }
            $pending = $r->offers()->where('status', 'pending')->lockForUpdate()->first();
            if ($pending && $pending->expires_at->isFuture()) {
                return $r;
            }
            if ($pending) {
                $pending->update(['status' => 'expired', 'responded_at' => now()]);
                $this->event($r, null, 'invitation_expired', ['volunteer_id' => $pending->volunteer_id]);
            }
            if (! $r->scheduled_start->isFuture()) {
                return $this->escalate($r, 'Service window started before assignment');
            }
            $tried = $r->offers()->where('match_round', $r->match_round)->pluck('volunteer_id')->all();
            foreach (User::whereHas('volunteerProfile')->whereNotIn('id', $tried)->orderBy('id')->get() as $candidate) {
                if ($this->ineligibility($candidate, $r)) {
                    continue;
                }
                $expires = now()->addMinutes(Setting::number('offer_minutes', 15))->min($r->scheduled_start);
                $offer = $r->offers()->create(['volunteer_id' => $candidate->id, 'match_round' => $r->match_round, 'status' => 'pending', 'expires_at' => $expires]);
                $r->update(['status' => 'offered', 'resolution_reason' => null]);
                $this->event($r, null, 'invitation_sent', ['offer_id' => $offer->id, 'volunteer_id' => $candidate->id]);
                $this->notify($candidate->id, $r, 'A neighbour needs your help', 'A matching invitation is ready. Open the app to respond.');
                $this->notify($r->requester_id, $r, 'Finding a helping hand', 'An eligible volunteer has been invited.');

                return $r;
            }

            return $this->escalate($r, 'No eligible volunteer accepted this request');
        }, 3);
    }

    private function escalate(HelpRequest $r, string $reason): HelpRequest
    {
        $r->update(['status' => 'escalated', 'resolution_reason' => $reason]);
        $this->event($r, null, 'escalated', ['reason' => $reason]);
        $this->notify($r->requester_id, $r, 'Community team notified', $reason.'. The administrator will review this request.');

        return $r;
    }

    public function accept(User $actor, int $offerId): HelpRequest
    {
        $offer = MatchOffer::findOrFail($offerId);
        abort_unless($offer->volunteer_id === $actor->id, 403);

        return DB::transaction(function () use ($actor, $offerId, $offer) {
            VolunteerProfile::where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $r = HelpRequest::lockForUpdate()->findOrFail($offer->help_request_id);
            $o = MatchOffer::lockForUpdate()->findOrFail($offerId);
            $this->check($o->status === 'pending' && $o->expires_at->isFuture() && $r->status === 'offered' && ! $r->volunteer_id && $o->match_round === $r->match_round, 'This invitation is no longer available.');
            $reasons = $this->ineligibility($actor, $r);
            $this->check(! $reasons, implode('. ', $reasons));
            $o->update(['status' => 'accepted', 'responded_at' => now()]);
            $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
            $r->update(['status' => 'accepted', 'volunteer_id' => $actor->id, 'assignment_version' => $r->assignment_version + 1, 'accepted_at' => now(), 'handover_hash' => null, 'handover_expires_at' => null, 'handover_attempts' => 0]);
            $this->event($r, $actor, 'accepted', ['assignment_version' => $r->assignment_version]);
            $this->notify($r->requester_id, $r, 'Your volunteer is confirmed', $actor->name.' accepted your request.');

            return $r;
        }, 3);
    }

    public function decline(User $actor, int $offerId): void
    {
        DB::transaction(function () use ($actor, $offerId) {
            $o = MatchOffer::findOrFail($offerId);
            abort_unless($o->volunteer_id === $actor->id, 403);
            $r = HelpRequest::lockForUpdate()->findOrFail($o->help_request_id);
            $o->refresh();
            $this->check($o->status === 'pending' && $o->expires_at->isFuture() && $r->status === 'offered', 'This invitation is no longer available.');
            $o->update(['status' => 'declined', 'responded_at' => now()]);
            $r->update(['status' => 'matching']);
            $this->event($r, $actor, 'invitation_declined');
            MatchRequest::dispatch($r->id)->afterCommit();
        });
    }

    public function progress(User $actor, HelpRequest $request, string $action): HelpRequest
    {
        return DB::transaction(function () use ($actor, $request, $action) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(! $actor->suspended && $r->volunteer_id === $actor->id, 403);
            $this->check($actor->volunteerProfile()->where('status', 'approved')->exists(), 'Your volunteer access is currently suspended.');
            $expected = $action === 'start' ? 'accepted' : 'in_progress';
            $this->check($r->status === $expected, 'This task cannot move to that step.');
            $r->update(['status' => $action === 'start' ? 'in_progress' : 'awaiting_confirmation']);
            $this->event($r, $actor, $action === 'start' ? 'task_started' : 'handover_requested');
            $this->notify($r->requester_id, $r, $action === 'start' ? 'Your task is underway' : 'Ready for handover', $action === 'start' ? 'Your volunteer has started.' : 'Check your items before sharing a handover code.');

            return $r;
        });
    }

    public function issueCode(User $actor, HelpRequest $request): array
    {
        return DB::transaction(function () use ($actor, $request) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless($this->authorized($actor, $r->beneficiary), 403);
            $this->check($r->status === 'awaiting_confirmation', 'A code can be generated only when the handover is ready.');
            $this->check($r->handover_attempts < 5, 'Code entry is locked. Ask the community team to review.');
            do {
                $code = (string) random_int(100000, 999999);
            } while ($r->handover_hash && Hash::check($r->id.':'.$r->assignment_version.':'.$code, $r->handover_hash));
            $expires = now()->addMinutes(10);
            $r->update(['handover_hash' => Hash::make($r->id.':'.$r->assignment_version.':'.$code), 'handover_expires_at' => $expires]);
            $this->event($r, $actor, 'handover_code_issued');

            return ['code' => $code, 'expires_at' => $expires->toIso8601String(), 'assignment_version' => $r->assignment_version];
        });
    }

    public function confirm(User $actor, HelpRequest $request, string $code): HelpRequest
    {
        $result = DB::transaction(function () use ($actor, $request, $code) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(! $actor->suspended && $r->volunteer_id === $actor->id, 403);
            $this->check($actor->volunteerProfile()->where('status', 'approved')->exists(), 'Your volunteer access is currently suspended.');
            $this->check($r->status === 'awaiting_confirmation', 'This task is not awaiting handover.');
            $this->check($r->handover_attempts < 5, 'Code entry is locked. Ask the community team to review.');
            $valid = $r->handover_hash && $r->handover_expires_at?->isFuture() && Hash::check($r->id.':'.$r->assignment_version.':'.$code, $r->handover_hash);
            if (! $valid) {
                $r->increment('handover_attempts');
                $this->event($r, $actor, 'handover_code_rejected');
                if ($r->handover_attempts >= 5) {
                    $this->escalate($r, 'Handover code attempt limit reached');
                }

                return null;
            }
            $r->update(['status' => 'completed', 'completion_kind' => 'verified_handover', 'completed_at' => now(), 'handover_hash' => null, 'handover_expires_at' => null]);
            $this->event($r, $actor, 'handover_verified');
            $this->notify($r->requester_id, $r, 'Thank you, handover complete', 'This task was completed with a one-time handover code.');

            return $r;
        });
        $this->check($result !== null, 'The code is incorrect or expired. Check with the recipient.');

        return $result;
    }

    public function cancel(User $actor, HelpRequest $request, string $reason): HelpRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless($this->canView($actor, $r), 403);
            $this->check(! in_array($r->status, ['completed', 'cancelled', 'closed']), 'This task is already closed.');
            $this->check(mb_strlen(trim($reason)) >= 5, 'Please explain the reason.');
            $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
            $r->update(['handover_hash' => null, 'handover_expires_at' => null]);
            if (in_array($r->status, ['in_progress', 'awaiting_confirmation']) || ($r->status === 'escalated' && $r->volunteer_id)) {
                $r = $this->escalate($r, 'Cancellation during an active handover: '.$reason);
            } elseif ($r->volunteer_id === $actor->id && ! $this->authorized($actor, $r->beneficiary)) {
                $r->update(['status' => 'matching', 'volunteer_id' => null, 'assignment_version' => $r->assignment_version + 1, 'resolution_reason' => $reason]);
                MatchRequest::dispatch($r->id)->afterCommit();
            } else {
                $r->update(['status' => 'cancelled', 'resolution_reason' => $reason]);
            }
            $this->event($r, $actor, 'cancellation_requested', ['reason' => $reason]);
            if ($r->volunteer_id) {
                $this->notify($r->volunteer_id, $r, 'Task needs your attention', 'The request was cancelled or escalated. Check its current status.');
            }

            return $r;
        });
    }

    public function rematch(User $admin, HelpRequest $request, string $reason): HelpRequest
    {
        $this->admin($admin);

        return DB::transaction(function () use ($admin, $request, $reason) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            $this->check(in_array($r->status, ['matching', 'offered', 'escalated']) && ! $r->volunteer_id, 'An assigned or active task must be resolved before rematching.');
            $this->check(strlen(trim($reason)) >= 5, 'A reason is required.');
            $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
            $r->update(['status' => 'matching', 'match_round' => $r->match_round + 1, 'handover_hash' => null, 'handover_expires_at' => null, 'resolution_reason' => $reason]);
            $this->event($r, $admin, 'admin_rematch', ['reason' => $reason]);
            $this->audit($admin, 'rematch', $r, ['reason' => $reason]);
            MatchRequest::dispatch($r->id)->afterCommit();

            return $r;
        });
    }

    public function assign(User $admin, HelpRequest $request, User $volunteer, string $reason): HelpRequest
    {
        $this->admin($admin);

        return DB::transaction(function () use ($admin, $request, $volunteer, $reason) {
            VolunteerProfile::where('user_id', $volunteer->id)->lockForUpdate()->firstOrFail();
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            $this->check(in_array($r->status, ['matching', 'offered', 'escalated']) && ! $r->volunteer_id, 'Only unassigned requests can be assigned.');
            $this->check(strlen(trim($reason)) >= 5, 'Record the reason and volunteer agreement.');
            $reasons = $this->ineligibility($volunteer, $r);
            $this->check(! $reasons, implode('. ', $reasons));
            $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
            $r->update(['status' => 'accepted', 'volunteer_id' => $volunteer->id, 'assignment_version' => $r->assignment_version + 1, 'accepted_at' => now(), 'resolution_reason' => $reason, 'handover_hash' => null, 'handover_expires_at' => null, 'handover_attempts' => 0]);
            $this->event($r, $admin, 'admin_assigned', ['volunteer_id' => $volunteer->id, 'reason' => $reason]);
            $this->audit($admin, 'assign', $r, ['reason' => $reason]);
            $this->notify($volunteer->id, $r, 'Community assignment', 'The community team assigned a task with your agreement.');
            $this->notify($r->requester_id, $r, 'Your volunteer is confirmed', $volunteer->name.' will help with your request.');

            return $r;
        }, 3);
    }

    public function close(User $admin, HelpRequest $request, string $reason): HelpRequest
    {
        $this->admin($admin);

        return DB::transaction(function () use ($admin, $request, $reason) {
            $r = HelpRequest::lockForUpdate()->findOrFail($request->id);
            $this->check(! in_array($r->status, ['completed', 'closed', 'cancelled']), 'This task is already closed.');
            $this->check(strlen(trim($reason)) >= 5, 'A resolution reason is required.');
            $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
            $r->update(['status' => 'closed', 'completion_kind' => 'admin_closed', 'resolution_reason' => $reason, 'handover_hash' => null, 'handover_expires_at' => null]);
            $this->event($r, $admin, 'admin_closed', ['reason' => $reason]);
            $this->audit($admin, 'close', $r, ['reason' => $reason]);
            $this->notify($r->requester_id, $r, 'Community review completed', $reason);

            return $r;
        });
    }

    public function reviewVolunteer(User $admin, VolunteerProfile $profile, string $status, string $note): void
    {
        $this->admin($admin);
        $this->check(in_array($status, ['approved', 'rejected', 'suspended']), 'Invalid review status.');
        $this->check(strlen(trim($note)) >= 5, 'Record the identity and service-area checks or reason.');
        DB::transaction(function () use ($admin, $profile, $status, $note) {
            $p = VolunteerProfile::lockForUpdate()->findOrFail($profile->id);
            $p->update(['status' => $status, 'verification_note' => $note, 'verified_by' => $admin->id, 'verified_at' => now()]);
            $this->audit($admin, 'volunteer_'.$status, $p, ['note' => $note]);
            if ($status !== 'approved') {
                foreach (HelpRequest::where('volunteer_id', $p->user_id)->whereIn('status', self::ACTIVE)->get() as $r) {
                    $r = HelpRequest::lockForUpdate()->findOrFail($r->id);
                    $this->escalate($r, 'Assigned volunteer access was suspended or rejected');
                    $r->update(['handover_hash' => null, 'handover_expires_at' => null]);
                }
            }
        });
    }

    public function reviewFamily(User $admin, FamilyAuthorization $authorization, string $status, string $note): void
    {
        $this->admin($admin);
        $this->check(in_array($status, ['approved', 'rejected', 'revoked']), 'Invalid authorization status.');
        $this->check(strlen(trim($note)) >= 5, 'Record how consent was checked, or the rejection/revocation reason.');
        DB::transaction(function () use ($admin, $authorization, $status, $note) {
            $a = FamilyAuthorization::lockForUpdate()->findOrFail($authorization->id);
            $a->update(['status' => $status, 'verified_by' => $admin->id, 'verified_at' => now()]);
            $this->audit($admin, 'family_'.$status, $a, ['note' => $note]);
            if ($status !== 'approved') {
                foreach (HelpRequest::where('beneficiary_id', $a->beneficiary_id)->where('requester_id', $a->user_id)->whereNotIn('status', ['completed', 'closed', 'cancelled'])->get() as $r) {
                    $r = HelpRequest::lockForUpdate()->findOrFail($r->id);
                    $r->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
                    $r->update(['handover_hash' => null, 'handover_expires_at' => null]);
                    $this->escalate($r, 'Family authorization was revoked; review required');
                }
            }
        });
    }

    public function report(User $actor, HelpRequest $r, string $description): Incident
    {
        abort_unless($this->canView($actor, $r), 403);

        return DB::transaction(function () use ($actor, $r, $description) {
            $request = HelpRequest::lockForUpdate()->findOrFail($r->id);
            $incident = Incident::create(['help_request_id' => $r->id, 'reporter_id' => $actor->id, 'description' => $description]);
            $this->event($request, $actor, 'incident_reported', ['incident_id' => $incident->id]);
            if (! in_array($request->status, ['completed', 'cancelled', 'closed'])) {
                $request->offers()->where('status', 'pending')->update(['status' => 'revoked', 'responded_at' => now()]);
                $request->update(['handover_hash' => null, 'handover_expires_at' => null]);
                $this->escalate($request, 'A concern was reported; community review required');
            }

            return $incident;
        });
    }

    public function resolveIncident(User $admin, Incident $incident, string $resolution): void
    {
        $this->admin($admin);
        $this->check(strlen(trim($resolution)) >= 5, 'A resolution is required.');
        DB::transaction(function () use ($admin, $incident, $resolution) {
            $incident->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $admin->id]);
            $this->audit($admin, 'incident_resolved', $incident, ['resolution' => $resolution]);
        });
    }

    public function sweep(): array
    {
        $matched = 0;
        $overdue = 0;
        $ids = HelpRequest::where('status', 'matching')->orWhere(fn ($q) => $q->where('status', 'offered')->whereHas('offers', fn ($o) => $o->where('status', 'pending')->where('expires_at', '<=', now())))->pluck('id');
        foreach ($ids as $id) {
            $this->match($id);
            $matched++;
        }
        foreach (HelpRequest::whereIn('status', self::ACTIVE)->where('scheduled_end', '<', now())->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$overdue) {
                $r = HelpRequest::lockForUpdate()->findOrFail($id);
                if (in_array($r->status, self::ACTIVE) && $r->scheduled_end->isPast()) {
                    $this->escalate($r, 'Service window overdue; administrator review required');
                    $r->update(['handover_hash' => null, 'handover_expires_at' => null]);
                    $overdue++;
                }
            });
        }

        return compact('matched', 'overdue');
    }

    public function stats(): array
    {
        return ['total' => HelpRequest::count(), 'awaiting_help' => HelpRequest::whereIn('status', ['matching', 'offered'])->count(), 'active' => HelpRequest::whereIn('status', self::ACTIVE)->count(), 'verified_completed' => HelpRequest::where('completion_kind', 'verified_handover')->count(), 'admin_closed' => HelpRequest::where('completion_kind', 'admin_closed')->count(), 'cancelled' => HelpRequest::where('status', 'cancelled')->count(), 'needs_attention' => HelpRequest::where('status', 'escalated')->count(), 'open_incidents' => Incident::where('status', 'open')->count(), 'pending_volunteers' => VolunteerProfile::where('status', 'pending')->count()];
    }
}
