<?php

use App\Mail\ReviewMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

function speaking(Client $client, array $languages): Client
{
    $client->forceFill(['profile' => ['languages' => $languages] + $client->profile])->save();

    return $client;
}

test('the switch puts the login in Greek and it sticks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('locale'), ['locale' => 'el'])->assertRedirect();

    expect($user->fresh()->locale)->toBe('el');
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()
        ->assertSee('<html lang="el">', false)->assertSee('Ημερολόγιο')->assertSee('English');
    $this->actingAs(User::factory()->create())->post(route('locale'), ['locale' => 'de'])->assertSessionHasErrors('locale');
});

test('client pages speak the client language unless the guest switches', function () {
    $greek = speaking(Client::factory()->create(), ['el', 'en']);
    $english = speaking(Client::factory()->create(), ['en']);
    $url = fn (Client $client) => URL::temporarySignedRoute('calendar.client', now()->addDay(), ['client' => $client->slug]);

    $this->get($url($greek))->assertOk()->assertSee('<html lang="el">', false)->assertSee('οι αναρτήσεις σας')->assertSee('English');
    $this->get($url($english))->assertOk()->assertSee('<html lang="en">', false)->assertSee('your posts');

    $this->post(route('locale'), ['locale' => 'en'])->assertRedirect();
    $this->get($url($greek))->assertOk()->assertSee('<html lang="en">', false)->assertSee('Ελληνικά');
});

test('approval mails go out in the client language, mails to staff in theirs', function () {
    $client = speaking(Client::factory()->create(), ['el', 'en']);
    $review = $client->reviews()->create(['due_at' => now()->addDay(), 'created_by' => User::factory()->create()->id]);

    $request = new ReviewMail($review, 'request');
    expect($request->locale)->toBe('el')->and($request->render())->toContain('Δείτε τις αναρτήσεις');
    expect((new ReviewMail($review, 'completed'))->locale)->toBe('en');
});

test('every interface string has a Greek translation with the same placeholders', function () {
    $el = json_decode(file_get_contents(lang_path('el.json')), true);

    $keys = [];
    foreach ([app_path(), resource_path('views'), base_path('routes')] as $dir) {
        foreach (File::allFiles($dir) as $file) {
            preg_match_all('/(?:__|trans_choice)\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1/', $file->getContents(), $m);
            $keys = array_merge($keys, array_map(fn ($k) => str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $k), $m[2]));
        }
    }
    $keys = array_filter(array_unique($keys), fn ($k) => ! preg_match('/\p{Greek}|^[a-z]+\.[a-z_]+$/u', $k)); // Greek examples and lang/el/*.php keys
    expect(count($keys))->toBeGreaterThan(400);
    expect(array_values(array_diff($keys, array_keys($el))))->toBe([]);

    foreach ($el as $en => $gr) {
        preg_match_all('/:\w+/', $en, $a);
        preg_match_all('/:\w+/', $gr, $b);
        sort($a[0]);
        sort($b[0]);
        expect($b[0])->toBe($a[0], $en)->and(substr_count($gr, '|'))->toBe(substr_count($en, '|'), $en);
    }
});
