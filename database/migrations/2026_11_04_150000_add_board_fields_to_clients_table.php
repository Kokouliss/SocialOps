<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// How the agency tracks each client on the Clients board (the team's "SoMe accounts" sheet): never shown to the client.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status', 20)->default('active');
            $table->string('owner', 60)->nullable(); // the colleague who runs the account
            $table->string('priority', 10)->nullable();
            $table->date('plan_due')->nullable(); // the next content plan is due by
            $table->unsignedTinyInteger('posts_per_week')->nullable();
            $table->unsignedTinyInteger('stories_per_week')->nullable();
            $table->unsignedTinyInteger('tiktoks_per_month')->nullable();
            $table->text('pending')->nullable();
            $table->json('links')->nullable(); // [{label, url}]: Drive folder, the posts presentation
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['status', 'owner', 'priority', 'plan_due', 'posts_per_week', 'stories_per_week', 'tiktoks_per_month', 'pending', 'links']);
        });
    }
};
