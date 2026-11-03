<?php

namespace App\Planning;

use Anthropic\Client;
use App\Drafting\Reply;

/** Asks Claude, with its web search tool, for the Markdown trend brief described in prompts/trend-research.md. */
class TrendResearcher
{
    public const MAX_SEARCHES = 12;

    public function __construct(private Client $claude) {}

    public function brief(PlanRequest $request): string
    {
        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => Prompt::render('trend-research', $request->promptVars() + ['max_searches' => self::MAX_SEARCHES])]],
            tools: [[
                'type' => 'web_search_20260209',
                'name' => 'web_search',
                'maxUses' => self::MAX_SEARCHES,
                // every client is in Crete, so bias results towards Greece
                'userLocation' => ['type' => 'approximate', 'country' => 'GR', 'timezone' => 'Europe/Athens'],
            ]],
        );

        return Reply::text($message);
    }
}
