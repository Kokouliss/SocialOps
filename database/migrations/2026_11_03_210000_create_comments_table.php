<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // comments other people left under a client's Instagram or Facebook posts, with the reply (draft until replied_at)
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20);
            $table->string('external_id', 80);
            $table->string('author', 120)->nullable();
            $table->text('text');
            $table->dateTime('posted_at');
            $table->text('reply')->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->dateTime('hidden_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
