<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DfsBlCategory extends Model
{
    protected $table = 'dfs_bl_categories';

    protected $fillable = [
        'category_code',
        'category_name',
        'business_count',
        'source',
        'raw_data',
        'collected_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'collected_at' => 'datetime',
    ];
}
