<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a client's connected social account: the OAuth token lives here, encrypted; never a password
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('external_id'); // IG user id, Page id, TikTok open_id, GBP accounts/{a}/locations/{l}
            $table->string('name');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->json('meta')->nullable(); // what the platform told us at connect time (TikTok creator options, …)
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['client_id', 'platform']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('brief'); // platform options: tiktok privacy and toggles, gbp event/offer fields
            $table->json('external_ids')->nullable()->after('published_at'); // platform => id of the published post
            $table->text('publish_error')->nullable()->after('external_ids');
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn(['settings', 'external_ids', 'publish_error']));
        Schema::dropIfExists('accounts');
    }
};
