<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $table = 'quiz_answers';

    protected $fillable = [
        'session_token',
        'region',
        'travelers_count',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'travelers_count' => 'integer',
    ];
}
