<?php

namespace App\Models;

use Anthropic\Messages\Message;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The token usage of one Claude API call, for the cost column on the Jobs page. */
#[Fillable(['client_id', 'purpose', 'model', 'input_tokens', 'output_tokens'])]
class ClaudeCall extends Model
{
    public static function record(Message $message, string $purpose, ?string $clientSlug = null): void
    {
        self::create([
            'client_id' => $clientSlug ? Client::where('slug', $clientSlug)->value('id') : null,
            'purpose' => $purpose,
            'model' => $message->model,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ]);
    }

    /** USD, or null until ANTHROPIC_PRICE_INPUT and ANTHROPIC_PRICE_OUTPUT (per million tokens) are set in .env. */
    public static function cost(int $inputTokens, int $outputTokens): ?float
    {
        $price = config('services.anthropic.price_per_mtok');
        if (! $price['input'] || ! $price['output']) {
            return null;
        }

        return $inputTokens / 1e6 * $price['input'] + $outputTokens / 1e6 * $price['output'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
