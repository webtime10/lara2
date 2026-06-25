<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->string('language', 10)->nullable()->index()->after('session_token');

            $table->string('trip_date_mode', 32)->nullable()->after('travelers_count');
            $table->string('trip_date_from', 32)->nullable()->after('trip_date_mode');
            $table->string('trip_date_to', 32)->nullable()->after('trip_date_from');
            $table->string('trip_duration_days', 16)->nullable()->after('trip_date_to');

            $table->unsignedTinyInteger('children_count')->default(0)->after('trip_duration_days');
            $table->json('children_ages')->nullable()->after('children_count');

            $table->string('housing_type', 64)->nullable()->after('region');
            $table->string('comfort_level', 64)->nullable()->after('housing_type');
            $table->string('entertainment_level', 64)->nullable()->after('comfort_level');
            $table->string('dining_level', 64)->nullable()->after('entertainment_level');
            $table->string('car_rental', 64)->nullable()->after('dining_level');
            $table->string('car_class', 64)->nullable()->after('car_rental');
            $table->string('budget_priority', 64)->nullable()->after('car_class');

            $table->string('budget_total', 64)->nullable()->after('payload');
            $table->string('budget_per_person', 64)->nullable()->after('budget_total');
            $table->text('budget_summary')->nullable()->after('budget_per_person');
            $table->json('budget_rows')->nullable()->after('budget_summary');

            $table->string('ai_model', 64)->nullable()->after('budget_rows');
            $table->boolean('ai_ok')->default(false)->after('ai_model');
            $table->text('ai_message')->nullable()->after('ai_ok');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->dropColumn([
                'language',
                'trip_date_mode',
                'trip_date_from',
                'trip_date_to',
                'trip_duration_days',
                'children_count',
                'children_ages',
                'housing_type',
                'comfort_level',
                'entertainment_level',
                'dining_level',
                'car_rental',
                'car_class',
                'budget_priority',
                'budget_total',
                'budget_per_person',
                'budget_summary',
                'budget_rows',
                'ai_model',
                'ai_ok',
                'ai_message',
            ]);
        });
    }
};
