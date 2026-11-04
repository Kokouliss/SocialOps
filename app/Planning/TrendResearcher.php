<?php

namespace App\Planning;

use Anthropic\Client;
use App\Drafting\Reply;
use App\Models\ClaudeCall;

/** Asks Claude, with its web search tool, for the Markdown trend brief described in prompts/trend-research.md. */
class TrendResearcher
{
    public const MAX_SEARCHES = 12;

    public function __construct(private Client $claude) {}

    public function brief(PlanRequest $request): string
    {
        $blocked = array_values(array_filter($request->profile['research']['excluded_domains'] ?? []));
        $message = $this->claude->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => Prompt::render('trend-research', $request->promptVars() + ['max_searches' => self::MAX_SEARCHES])]],
            tools: [[
                'type' => 'web_search_20260209',
                'name' => 'web_search',
                'maxUses' => self::MAX_SEARCHES,
                // no userLocation: the API rejects Greece ("Country code GR is not supported"); the prompt names the client's place and Crete
                ...($blocked ? ['blockedDomains' => $blocked] : []),
            ]],
        );

        ClaudeCall::record($message, 'trend_brief', $request->profile['slug']);

        return Reply::text($message);
    }
}
