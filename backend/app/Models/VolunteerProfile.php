<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolunteerProfile extends Model
{
    protected $fillable = ['user_id', 'status', 'zone_ids', 'category_ids', 'availability', 'max_tasks', 'verification_note', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['zone_ids' => 'array', 'category_ids' => 'array', 'availability' => 'array', 'max_tasks' => 'integer', 'verified_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
