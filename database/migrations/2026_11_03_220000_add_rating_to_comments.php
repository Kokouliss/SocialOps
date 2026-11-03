<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Google Business Profile reviews share the comments inbox; the star rating is what sets them apart
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('text');
        });
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $table) => $table->dropColumn('rating'));
    }
};
