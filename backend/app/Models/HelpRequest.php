<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpRequest extends Model
{
    protected $fillable = ['requester_id', 'beneficiary_id', 'service_category_id', 'zone_id', 'title', 'notes', 'pickup_address', 'delivery_address', 'contact_phone', 'scheduled_start', 'scheduled_end', 'status', 'volunteer_id', 'assignment_version', 'match_round', 'resolution_reason', 'completion_kind', 'handover_hash', 'handover_expires_at', 'handover_attempts', 'accepted_at', 'completed_at'];

    protected function casts(): array
    {
        return ['scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'handover_expires_at' => 'datetime', 'accepted_at' => 'datetime', 'completed_at' => 'datetime', 'assignment_version' => 'integer', 'match_round' => 'integer'];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function volunteer()
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function offers()
    {
        return $this->hasMany(MatchOffer::class);
    }

    public function events()
    {
        return $this->hasMany(RequestEvent::class);
    }

    protected $hidden = ['handover_hash'];
}
