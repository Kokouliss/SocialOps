<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null: every client; a list of client ids: this login sees only those
        Schema::table('users', fn (Blueprint $table) => $table->json('client_ids')->nullable()->after('password'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('client_ids'));
    }
};
