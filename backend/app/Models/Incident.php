<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = ['help_request_id', 'reporter_id', 'description', 'status', 'resolution', 'resolved_by'];

    protected function casts(): array
    {
        return [];
    }

    public function helpRequest()
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
