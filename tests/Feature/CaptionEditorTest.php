<?php

use App\Drafting\CaptionEditor;
use App\Models\ClaudeCall;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;

it('asks Claude to rewrite every caption as instructed, with the limits and the brief, and records the call', function () {
    $profile = Client::factory()->create()->toProfileArray();
    $editor = new CaptionEditor(fakeClaude(json_encode(['instagram' => ['el' => ' Κοκτέιλ με θέα ', 'en' => 'Cocktails with a view'], 'facebook' => ['el' => 'Κοκτέιλ', 'en' => 'Cocktails']], JSON_UNESCAPED_UNICODE)));

    $out = $editor->rewrite($profile, 'Make it playful', ['instagram' => ['el' => 'Κοκτέιλ', 'en' => ''], 'facebook' => ['el' => 'Κοκτέιλ', 'en' => 'Cocktails']], 'Sunset bar opening');

    $body = json_decode((string) claudeRequests()[0]['request']->getBody(), true);
    expect($out)->toBe(['instagram' => ['el' => 'Κοκτέιλ με θέα', 'en' => 'Cocktails with a view'], 'facebook' => ['el' => 'Κοκτέιλ', 'en' => 'Cocktails']])
        ->and($body['messages'][0]['content'])->toContain('Make it playful', 'Sunset bar opening', '## instagram (at most 2200 characters)', "### en\n(empty)", '## facebook (at most 63206 characters)')
        ->and($body['output_config']['format']['schema']['properties']['instagram']['required'])->toBe(['el', 'en'])
        ->and(ClaudeCall::first()->purpose)->toBe('rewrite');
});

test('the post form has the "Change with Claude" box and the rewrite endpoint validates and returns the new captions as JSON', function () {
    $post = Post::factory()->create(['platforms' => ['instagram']]);
    $me = User::factory()->create();
    $this->actingAs($me)->get(route('posts.edit', $post))->assertOk()->assertSee('Change with Claude')->assertSee(route('posts.rewrite', $post));
    $this->actingAs($me)->get(route('posts.create', $post->client))->assertOk()->assertDontSee('Change with Claude');

    $this->mock(CaptionEditor::class)->shouldReceive('rewrite')->once()
        ->withArgs(fn ($profile, $instruction, $captions, $brief) => $instruction === 'Shorter' && $captions === ['instagram' => ['el' => 'μακρύ', 'en' => '']] && $brief === 'Sea bass')
        ->andReturn(['instagram' => ['el' => 'κοντό', 'en' => 'short']]);
    $this->actingAs($me)->postJson(route('posts.rewrite', $post), ['instruction' => ' Shorter ', 'brief' => 'Sea bass', 'captions' => ['instagram' => ['el' => ' μακρύ ', 'en' => null]]])
        ->assertOk()->assertExactJson(['instagram' => ['el' => 'κοντό', 'en' => 'short']]);
    $this->actingAs($me)->postJson(route('posts.rewrite', $post), ['instruction' => '', 'captions' => ['instagram' => ['el' => 'x']]])->assertStatus(422)->assertJsonValidationErrors('instruction');
    $this->actingAs($me)->postJson(route('posts.rewrite', $post), ['instruction' => 'x', 'captions' => ['linkedin' => ['el' => 'x']]])->assertStatus(422)->assertJsonValidationErrors('captions');
    $this->actingAs($me)->postJson(route('posts.rewrite', $post), ['instruction' => 'x', 'captions' => ['instagram' => ['de' => 'x']]])->assertStatus(422)->assertJsonValidationErrors('captions.instagram');
});
