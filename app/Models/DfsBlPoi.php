<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DfsBlPoi extends Model
{
    protected $table = 'dfs_bl_pois';

    protected $fillable = [
        'destination_slug',
        'location_code',
        'external_id',
        'dedup_hash',
        'name',
        'title',
        'primary_category',
        'latitude',
        'longitude',
        'address',
        'city',
        'region',
        'postal_code',
        'country_code',
        'rating',
        'reviews_count',
        'phone',
        'website',
        'working_hours',
        'source',
        'raw_data',
        'collected_at',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'raw_data' => 'array',
        'collected_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'rating' => 'float',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(DfsBlPoiCategory::class, 'poi_id');
    }
}
