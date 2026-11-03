<?php

namespace App\Mcp;

use App\Mcp\Tools\CreateDraft;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\ListClients;
use App\Mcp\Tools\ListComments;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\MonthNumbers;
use App\Mcp\Tools\ReplyToComment;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/** The MCP server (docs/api.md): the same reads and writes as the JSON API, for Claude Code and Claude Desktop. Never publishes. */
#[Name('Social Ops')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
Social Ops plans, drafts, approves and publishes social media posts for an agency's clients (hospitality in Crete and Cyprus, Greek and English).
Start with list-clients, then use the client's id or slug in the other tools. Captions go per platform and language under variants.
A Draft you create still goes through human review and approval in the app; nothing here publishes. Greek captions are reviewed by a person.
MD)]
class SocialOpsServer extends Server
{
    protected array $tools = [
        ListClients::class,
        ListPosts::class,
        GetPost::class,
        CreateDraft::class,
        ListComments::class,
        ReplyToComment::class,
        MonthNumbers::class,
    ];
}
