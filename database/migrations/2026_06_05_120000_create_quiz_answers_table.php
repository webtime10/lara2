<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('region', 128)->nullable()->index();
            $table->unsignedTinyInteger('travelers_count')->default(0);
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
