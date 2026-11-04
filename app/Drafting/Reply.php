<?php

namespace App\Drafting;

use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use RuntimeException;
use Throwable;

/** Reads a Claude reply: joined text, or the JSON it was asked for. Refusals and cut-off replies throw. */
class Reply
{
    public static function text(Message $message): string
    {
        if ($message->stopReason !== 'end_turn') {
            // shortcut: pause_turn (very long web searches) is not resumed, the command is simply rerun; add a continuation loop if it shows up in the logs
            $why = $message->stopDetails?->explanation;
            throw new RuntimeException("Claude stopped with reason '{$message->stopReason}'".($why ? ": {$why}" : ''));
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    public static function json(Message $message): array
    {
        return json_decode(self::text($message), true, 512, JSON_THROW_ON_ERROR);
    }

    /** One line for a human: the API's own message when Claude refused the request (the SDK buries it in a JSON dump), else the exception's. */
    public static function error(?Throwable $e): string
    {
        return $e instanceof APIStatusException ? 'Claude: '.(data_get($e->body, 'error.message') ?? $e->getMessage()) : ($e?->getMessage() ?? 'unknown error');
    }
}
