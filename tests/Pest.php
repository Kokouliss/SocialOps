<?php

use Anthropic\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** A Claude client whose replies are canned: one text block per call, in order. */
function fakeClaude(string ...$texts): Client
{
    $responses = array_map(fn (string $text) => new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => 'end_turn',
        'stop_details' => null,
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 1, 'output_tokens' => 1, 'cache_creation' => null, 'cache_creation_input_tokens' => null,
            'cache_read_input_tokens' => null, 'inference_geo' => null, 'output_tokens_details' => null,
            'server_tool_use' => null, 'service_tier' => 'standard', 'speed' => 'standard',
        ],
    ])), $texts);

    global $claudeRequests;
    $claudeRequests = [];
    $handler = HandlerStack::create(new MockHandler($responses));
    $handler->push(Middleware::history($claudeRequests));

    return new Client(apiKey: 'test', requestOptions: ['transporter' => new GuzzleHttp\Client(['handler' => $handler])]);
}

/**
 * Every request the last fakeClaude() client sent, in order.
 *
 * @return list<array{request: RequestInterface}>
 */
function claudeRequests(): array
{
    global $claudeRequests;

    return $claudeRequests;
}
