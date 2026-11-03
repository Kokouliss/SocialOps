<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique(); // CLIENT_YYYYMMDD_PLATFORM_FORMAT_PILLAR_LANG_v#
            $table->string('state', 20)->index();
            $table->json('platforms');
            $table->string('format', 20);
            $table->string('pillar', 40)->nullable();
            $table->text('brief')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('post_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('lang', 2);
            $table->text('caption')->nullable();
            $table->string('alt_text', 1000)->nullable();
            $table->json('hashtags');
            $table->timestamps();
            $table->unique(['post_id', 'platform', 'lang']);
        });

        Schema::create('asset_post', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['post_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_post');
        Schema::dropIfExists('post_variants');
        Schema::dropIfExists('posts');
    }
};
