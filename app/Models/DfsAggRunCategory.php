<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DfsAggRunCategory extends Model
{
    protected $table = 'dfs_agg_run_categories';

    protected $fillable = [
        'run_id',
        'destination',
        'location_code',
        'category_code',
        'category_name',
        'objects_count',
        'api_cost',
        'raw_data',
        'collected_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'api_cost' => 'float',
        'collected_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(DfsAggRun::class, 'run_id');
    }
}
