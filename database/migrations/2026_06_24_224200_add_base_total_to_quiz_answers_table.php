<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_answers', 'budget_base_total')) {
                $table->string('budget_base_total', 64)->nullable()->after('car_budget_total');
            }
            if (! Schema::hasColumn('quiz_answers', 'base_total')) {
                $table->decimal('base_total', 15, 2)->nullable()->after('budget_base_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_answers', 'base_total')) {
                $table->dropColumn('base_total');
            }
            if (Schema::hasColumn('quiz_answers', 'budget_base_total')) {
                $table->dropColumn('budget_base_total');
            }
        });
    }
};
