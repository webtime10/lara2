<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPromt extends Model
{
    protected $table = 'budget_promt';

    protected $fillable = ['name', 'content'];
}
