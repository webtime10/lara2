<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $table = 'quiz_answers';

    protected $fillable = [
        'session_token',
        'language',
        'trip_date_mode',
        'trip_date_from',
        'trip_date_to',
        'trip_duration_days',
        'total_days',
        'trip_months',
        'travelers_count',
        'children_count',
        'total_people',
        'children_ages',
        'region',
        'housing_type',
        'comfort_level',
        'entertainment_level',
        'dining_level',
        'car_rental',
        'car_class',
        'budget_priority',
        'payload',
        'budget_total',
        'total',
        'housing_budget_total',
        'entertainment_budget_total',
        'food_budget_total',
        'car_budget_total',
        'budget_base_total',
        'base_total',
        'budget_priority_adjustment_total',
        'calculation_status',
        'calculation_error',
        'calculation_started_at',
        'calculation_completed_at',
        'budget_per_person',
        'budget_summary',
        'budget_rows',
        'ai_model',
        'ai_ok',
        'ai_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'children_ages' => 'array',
        'budget_rows' => 'array',
        'travelers_count' => 'integer',
        'children_count' => 'integer',
        'total_people' => 'integer',
        'base_total' => 'decimal:2',
        'total' => 'decimal:2',
        'total_days' => 'integer',
        'ai_ok' => 'boolean',
        'calculation_started_at' => 'datetime',
        'calculation_completed_at' => 'datetime',
    ];
}
