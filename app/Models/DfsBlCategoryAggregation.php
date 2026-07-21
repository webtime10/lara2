<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DfsBlCategoryAggregation extends Model
{
    protected $table = 'dfs_bl_category_aggregations';

    protected $fillable = [
        'destination_slug',
        'location_code',
        'category_code',
        'category_name',
        'objects_count',
        'source',
        'raw_data',
        'collected_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'collected_at' => 'datetime',
    ];
}
