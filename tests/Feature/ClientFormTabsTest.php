<?php

use App\Models\Client;
use App\Models\User;

test('the profile form is six tabs with every field still in the page', function () {
    $client = Client::factory()->create();
    $html = $this->actingAs(User::factory()->create())->get(route('clients.edit', $client))->assertOk()->getContent();

    expect(substr_count($html, 'role="tab"'))->toBe(6);
    foreach (['basics', 'agency', 'voice', 'approval', 'research', 'more'] as $tab) {
        expect($html)->toContain("x-show=\"tab === '{$tab}'\"");
    }
    expect($html)->toContain('name="name"')->toContain('name="plan_due"')->toContain('name="p[signatures][instagram]"')->toContain('name="p[slots][0][day]"')
        ->toContain('name="p[research][sources]"')->toContain('name="p[saved_replies][0][label]"')->toContain('name="p[webhooks][0][url]"');
});
