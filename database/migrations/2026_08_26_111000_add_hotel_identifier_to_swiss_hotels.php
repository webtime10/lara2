<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swiss_hotels', function (Blueprint $table) {
            if (! Schema::hasColumn('swiss_hotels', 'hotel_identifier')) {
                $table->string('hotel_identifier', 255)->nullable()->after('title');
                $table->index('hotel_identifier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('swiss_hotels', function (Blueprint $table) {
            if (Schema::hasColumn('swiss_hotels', 'hotel_identifier')) {
                $table->dropIndex(['hotel_identifier']);
                $table->dropColumn('hotel_identifier');
            }
        });
    }
};
