<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyAuthorization extends Model
{
    protected $fillable = ['beneficiary_id', 'user_id', 'relationship', 'status', 'consent_note', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
