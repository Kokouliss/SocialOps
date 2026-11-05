<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // link in bio is gone (D19): the WordPress application passwords it stored have no use left, so they do not stay in the database
        DB::table('accounts')->where('platform', 'wordpress')->delete();
    }

    public function down(): void
    {
        // the passwords are not coming back
    }
};
