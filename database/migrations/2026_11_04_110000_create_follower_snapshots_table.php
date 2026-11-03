<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one follower count per account per day, taken by posts:insights; the monthly report reads the month's change from it
        Schema::create('follower_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->unsignedInteger('followers');
            $table->unique(['account_id', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follower_snapshots');
    }
};
