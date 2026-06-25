<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_answers', 'budget_priority_adjustment_total')) {
                $table->string('budget_priority_adjustment_total', 64)->nullable()->after('car_budget_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_answers', 'budget_priority_adjustment_total')) {
                $table->dropColumn('budget_priority_adjustment_total');
            }
        });
    }
};
