<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestEvent extends Model
{
    protected $fillable = ['help_request_id', 'actor_id', 'action', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
