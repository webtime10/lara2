<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherMonthStat extends Model
{
    protected $fillable = [
        'region_slug',
        'region_name_ru',
        'month',
        'average_temperature',
        'precipitation',
        'sunny_days',
        'season',
        'ai_model',
        'last_checked',
    ];

    protected $casts = [
        'month' => 'integer',
        'last_checked' => 'datetime',
    ];

    public function isFilled(): bool
    {
        return trim((string) $this->average_temperature) !== ''
            || trim((string) $this->precipitation) !== ''
            || trim((string) $this->sunny_days) !== ''
            || trim((string) $this->season) !== '';
    }

    public function shortLabel(): string
    {
        $temp = trim((string) $this->average_temperature);
        $season = trim((string) $this->season);

        if ($temp !== '' && $season !== '') {
            return $temp.' · '.$season;
        }

        return $temp !== '' ? $temp : ($season !== '' ? $season : '✓');
    }
}
