<?php

// Claude adds Social Ops as a connector through OAuth (routes/ai.php, Laravel Passport): where its login may be sent back to
return [
    'redirect_domains' => ['https://claude.ai', 'https://claude.com', 'http://localhost'], // claude.ai, the desktop app and Claude Code
    'custom_schemes' => [],
    'authorization_server' => null,
];
