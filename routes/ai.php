<?php

use App\Http\Middleware\ApiAuth;
use App\Mcp\SocialOpsServer;
use Laravel\Mcp\Facades\Mcp;

// Claude Code or Claude Desktop works on Social Ops through here, with an API token from the Staff page (docs/api.md)
Mcp::web('/mcp', SocialOpsServer::class)->middleware([ApiAuth::class, 'throttle:api']);

// The same server over stdio for a Claude Code running on the server itself: php artisan mcp:start socialops
Mcp::local('socialops', SocialOpsServer::class);
