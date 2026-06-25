<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swiss_regions', function (Blueprint $table) {
            $table->timestamp('entertainments_synced_at')->nullable()->after('apartments_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('swiss_regions', function (Blueprint $table) {
            $table->dropColumn('entertainments_synced_at');
        });
    }
};
