<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DfsBlLocationCandidate extends Model
{
    protected $table = 'dfs_bl_location_candidates';

    protected $fillable = [
        'destination_slug',
        'location_code',
        'location_name',
        'location_type',
        'country_iso_code',
        'is_selected',
        'selection_reason',
        'raw_data',
        'collected_at',
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'raw_data' => 'array',
        'collected_at' => 'datetime',
    ];
}
