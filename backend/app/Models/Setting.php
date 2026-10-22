<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [];
    }

    public static function number(string $key, int $default): int
    {
        return (int) (static::where('key', $key)->value('value') ?? $default);
    }
}
