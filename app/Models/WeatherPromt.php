<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherPromt extends Model
{
    protected $table = 'weather_promt';

    protected $fillable = ['name', 'content'];
}
