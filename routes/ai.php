<?php

use App\Http\Middleware\McpAuth;
use App\Mcp\SocialOpsServer;
use Laravel\Mcp\Facades\Mcp;

// Claude works on Social Ops through here: claude.ai and the desktop app as a custom connector (OAuth login below),
// Claude Code with the same login or an API token from the Staff page (docs/api.md)
Mcp::web('/mcp', SocialOpsServer::class)->middleware([McpAuth::class, 'throttle:api']);
Mcp::oauthRoutes(); // the discovery documents and client registration Claude needs; Passport's /oauth/authorize and /oauth/token do the login

// The same server over stdio for a Claude Code running on the server itself: php artisan mcp:start socialops
Mcp::local('socialops', SocialOpsServer::class);
