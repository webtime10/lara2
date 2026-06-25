<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwissEntertainmentSyncState extends Model
{
    protected $table = 'swiss_entertainment_sync_state';

    protected $fillable = [
        'last_full_sync_at',
    ];

    protected $casts = [
        'last_full_sync_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
