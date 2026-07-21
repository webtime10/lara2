<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DfsBlPoiCategory extends Model
{
    protected $table = 'dfs_bl_poi_categories';

    protected $fillable = [
        'poi_id',
        'category_code',
        'category_name',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function poi(): BelongsTo
    {
        return $this->belongsTo(DfsBlPoi::class, 'poi_id');
    }
}
