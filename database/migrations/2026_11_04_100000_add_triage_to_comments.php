<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Claude's triage at fetch time: the inbox shows what needs attention first, Slack says how many
        Schema::table('comments', function (Blueprint $table) {
            $table->string('sentiment', 10)->nullable()->after('rating');
            $table->string('intent', 20)->nullable()->after('sentiment');
        });
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $table) => $table->dropColumn(['sentiment', 'intent']));
    }
};
