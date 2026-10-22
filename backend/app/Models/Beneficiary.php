<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    protected $fillable = ['user_id', 'created_by', 'name', 'phone', 'address', 'zone_id'];

    protected function casts(): array
    {
        return [];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function authorizations()
    {
        return $this->hasMany(FamilyAuthorization::class);
    }
}
