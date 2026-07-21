<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DfsBlTouristCategoryMatch extends Model
{
    protected $table = 'dfs_bl_tourist_category_matches';

    protected $fillable = [
        'topic_group',
        'category_code',
        'category_name',
        'matched',
        'match_reason',
        'collected_at',
    ];

    protected $casts = [
        'matched' => 'boolean',
        'collected_at' => 'datetime',
    ];
}
