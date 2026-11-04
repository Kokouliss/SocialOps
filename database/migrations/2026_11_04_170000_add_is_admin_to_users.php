<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false)->after('client_ids'));

        // Panos's login (2026-11-04); failing that the oldest login, so the Settings page is never out of reach
        if (! DB::table('users')->where('email', 'manouselis@inglelandi.com')->update(['is_admin' => true]) && ($first = DB::table('users')->orderBy('id')->value('id'))) {
            DB::table('users')->where('id', $first)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
