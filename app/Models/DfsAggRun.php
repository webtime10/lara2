<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DfsAggRun extends Model
{
    protected $table = 'dfs_agg_runs';

    protected $fillable = [
        'destination',
        'destination_slug',
        'location_code',
        'location_coordinate',
        'endpoint',
        'categories_selected',
        'categories_processed',
        'api_requests',
        'total_objects_reported',
        'api_cost',
        'execution_time_ms',
        'status',
        'error_message',
        'meta',
        'collected_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'api_cost' => 'float',
        'collected_at' => 'datetime',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(DfsAggRunCategory::class, 'run_id');
    }
}
