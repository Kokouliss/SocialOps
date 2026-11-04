<?php

return [
    /*
    | Posting to the platforms through our own drivers: the accounts page and OAuth, the publish queue, the token
    | monitor, preflight, reconcile, Withdraw, and everything that reads back from the platform APIs (comments,
    | messages, reviews, insights, the monthly numbers, the listening report). Off since 2026-11-04 (D16): Social Ops
    | organizes the work and the team posts in the apps by hand. The code stays for the day it comes back.
    */
    'publishing' => (bool) env('FEATURE_PUBLISHING', false),

    /*
    | Claude called from the server with ANTHROPIC_API_KEY: the "Plan with Claude", "Post from a prompt", "Draft captions",
    | "Shorten" and "Change with Claude" buttons and the content:plan command's research and captions. Off since 2026-11-04
    | (D18): Claude works through the MCP connector instead (docs/api.md), the planning tools hand it the brief and file
    | its plan. Back on when the team wants the buttons again.
    */
    'claude_api' => (bool) env('FEATURE_CLAUDE_API', false),
];
