<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwissRegion extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'location_code',
        'hotels_synced_at',
        'apartments_synced_at',
        'entertainments_synced_at',
    ];

    protected $casts = [
        'location_code' => 'integer',
        'hotels_synced_at' => 'datetime',
        'apartments_synced_at' => 'datetime',
        'entertainments_synced_at' => 'datetime',
    ];

    public function hotels(): HasMany
    {
        return $this->hasMany(SwissHotel::class, 'region_id');
    }

    public function apartments(): HasMany
    {
        return $this->hasMany(SwissApartment::class, 'region_id');
    }

    public function entertainments(): HasMany
    {
        return $this->hasMany(SwissEntertainment::class, 'region_id');
    }

    public function foodSources(): HasMany
    {
        return $this->hasMany(FoodSource::class, 'region_id');
    }

    public function foodImports(): HasMany
    {
        return $this->hasMany(FoodImport::class, 'region_id');
    }

    public function foodSamples(): HasMany
    {
        return $this->hasMany(FoodSample::class, 'region_id');
    }
}
