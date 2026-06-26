<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_answers', 'calculation_status')) {
                $table->string('calculation_status', 32)->default('completed')->after('budget_priority_adjustment_total');
            }
            if (! Schema::hasColumn('quiz_answers', 'calculation_error')) {
                $table->text('calculation_error')->nullable()->after('calculation_status');
            }
            if (! Schema::hasColumn('quiz_answers', 'calculation_started_at')) {
                $table->timestamp('calculation_started_at')->nullable()->after('calculation_error');
            }
            if (! Schema::hasColumn('quiz_answers', 'calculation_completed_at')) {
                $table->timestamp('calculation_completed_at')->nullable()->after('calculation_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            foreach (['calculation_completed_at', 'calculation_started_at', 'calculation_error', 'calculation_status'] as $column) {
                if (Schema::hasColumn('quiz_answers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
