<?php

namespace App\Services;

use App\Models\HelpRequest;
use App\Models\User;

class RequestPresenter
{
    public function summary(HelpRequest $r): array
    {
        return ['id' => $r->id, 'title' => $r->title, 'category' => $r->category->name, 'zone' => $r->zone->name, 'status' => $r->status, 'scheduled_start' => $r->scheduled_start->toIso8601String(), 'scheduled_end' => $r->scheduled_end->toIso8601String(), 'created_at' => $r->created_at->toIso8601String()];
    }

    public function detail(HelpRequest $r, User $viewer): array
    {
        abort_unless(app(CareService::class)->canView($viewer, $r), 403);
        $owner = app(CareService::class)->authorized($viewer, $r->beneficiary);
        $data = $this->summary($r) + ['notes' => $r->notes, 'beneficiary_name' => $r->beneficiary->name, 'pickup_address' => $r->pickup_address, 'delivery_address' => $r->delivery_address, 'contact_phone' => $r->contact_phone, 'volunteer' => $r->volunteer?->only(['id', 'name', 'phone']), 'assignment_version' => $r->assignment_version, 'resolution_reason' => $r->resolution_reason, 'completion_kind' => $r->completion_kind, 'is_owner' => $owner, 'is_assigned' => $r->volunteer_id === $viewer->id, 'handover_locked' => $r->handover_attempts >= 5, 'events' => $r->events()->orderBy('id')->get()->map(fn ($e) => ['id' => $e->id, 'action' => $e->action, 'at' => $e->created_at->toIso8601String()])];
        if ($viewer->is_admin) {
            $data['offers'] = $r->offers()->with('volunteer:id,name')->get();
        }

        return $data;
    }
}
