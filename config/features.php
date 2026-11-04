<?php

return [
    /*
    | Posting to the platforms through our own drivers: the accounts page and OAuth, the publish queue, the token
    | monitor, preflight, reconcile, Withdraw, and everything that reads back from the platform APIs (comments,
    | messages, reviews, insights, the monthly numbers, the listening report). Off since 2026-11-04 (D16): Social Ops
    | organizes the work and the team posts in the apps by hand. The code stays for the day it comes back.
    */
    'publishing' => (bool) env('FEATURE_PUBLISHING', false),
];
