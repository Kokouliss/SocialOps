<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // set on a direct message: the sender's id, which the reply goes to
        Schema::table('comments', fn (Blueprint $table) => $table->string('thread_id', 80)->nullable()->after('external_id'));
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $table) => $table->dropColumn('thread_id'));
    }
};
