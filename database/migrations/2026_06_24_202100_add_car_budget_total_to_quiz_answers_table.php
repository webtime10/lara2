<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_answers', 'car_budget_total')) {
                $table->string('car_budget_total', 64)->nullable()->after('food_budget_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_answers', 'car_budget_total')) {
                $table->dropColumn('car_budget_total');
            }
        });
    }
};
