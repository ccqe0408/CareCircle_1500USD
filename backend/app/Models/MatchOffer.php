<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchOffer extends Model
{
    protected $fillable = ['help_request_id', 'volunteer_id', 'match_round', 'status', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function helpRequest()
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function volunteer()
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }
}
