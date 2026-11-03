<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique(); // CLIENT_YYYYMMDD_ANY_IMG_PILLAR_LANG_v1.jpg
            $table->string('original_name');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('kind', 10); // image | video
            $table->string('pillar', 40)->nullable();
            $table->string('season', 30)->nullable();
            $table->boolean('people_consent')->default(false);
            $table->boolean('ugc_permission')->default(false);
            $table->string('note', 500)->nullable();
            $table->string('uploaded_via', 10); // link | staff
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
