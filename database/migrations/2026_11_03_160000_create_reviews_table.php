<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one review = one batch of posts sent to the client's approvers under one signed link
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->dateTime('due_at');
            $table->dateTime('reminded_at')->nullable();
            $table->dateTime('escalated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('post_review', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 20)->nullable(); // approved | changes_requested
            $table->text('comment')->nullable();
            $table->string('decided_by', 100)->nullable(); // approver's name, or "auto" on timeout
            $table->dateTime('decided_at')->nullable();
            $table->primary(['review_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_review');
        Schema::dropIfExists('reviews');
    }
};
