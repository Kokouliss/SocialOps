<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // per platform: reach, views, likes, comments, shares, saved, fetched_at; read back daily for 30 days by posts:insights
        Schema::table('posts', fn (Blueprint $table) => $table->json('metrics')->nullable()->after('publish_error'));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('metrics'));
    }
};
