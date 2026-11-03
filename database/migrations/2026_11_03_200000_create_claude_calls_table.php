<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row per Claude API call, so the Jobs page can show what the drafting costs per client
        Schema::create('claude_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 40); // caption | plan | trend_brief
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_calls');
    }
};
