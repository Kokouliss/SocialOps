<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Client;
use App\Models\Post;
use App\Models\User;
use App\Posts\PostState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Post today: the posts due today (or tomorrow) across the login's clients, each with its media and the exact texts to paste
 * into the app, then "Mark published". Made for posting by hand from a phone while posting through the drivers is off.
 */
class TodayController extends Controller
{
    /** A post in one of these states waits to be posted; Manual needed only happens while posting is on. */
    public const TO_POST = [PostState::Approved, PostState::Scheduled, PostState::ManualNeeded];

    /** How many days back a post that was never marked published still shows on today's list, flagged late. */
    public const LATE_DAYS = 7;

    public function index(Request $request): View
    {
        $request->validate(['day' => ['nullable', 'in:today,tomorrow'], 'client' => ['nullable', 'string']]);
        $tomorrow = $request->input('day') === 'tomorrow';
        $day = $tomorrow ? today()->addDay() : today();
        $clients = $request->user()->clients()->get();
        $selected = $clients->firstWhere('slug', $request->input('client'));

        return view('today.index', [
            'day' => $day,
            'tomorrow' => $tomorrow,
            'clients' => $clients,
            'selected' => $selected,
            'todo' => self::toPost($request->user(), $day, $selected)->get()->map(fn (Post $post) => ['post' => $post, 'platforms' => self::texts($post)]),
            'done' => self::done($request->user(), $day, $selected)->get(),
        ]);
    }

    /** The published posts due that day; today also the ones marked published today, a late one or one posted early. */
    private static function done(User $user, CarbonInterface $day, ?Client $client): Builder
    {
        $span = [$day->copy()->startOfDay(), $day->copy()->endOfDay()];

        return self::posts($user, $client)->where('state', PostState::Published)
            ->where(fn ($q) => $q->whereBetween('scheduled_at', $span)->when($day->isToday(), fn ($q) => $q->orWhereBetween('published_at', $span)));
    }

    /** The posts still to post on that day; today also keeps the last days' posts nobody marked published (posted and not marked, or not posted at all). */
    public static function toPost(User $user, CarbonInterface $day, ?Client $client = null): Builder
    {
        $from = $day->isToday() ? $day->copy()->subDays(self::LATE_DAYS) : $day;

        return self::posts($user, $client)->whereIn('state', self::TO_POST)->whereBetween('scheduled_at', [$from->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    /** The login's posts (LimitClients), binned ones left out by the soft deletes, soonest first. */
    private static function posts(User $user, ?Client $client): Builder
    {
        return $user->restrict(Post::with('client', 'variants', 'assets'))
            ->when($client, fn ($q) => $q->where('client_id', $client->id))
            ->orderBy('scheduled_at')->orderBy('id');
    }

    /**
     * Per ticked platform what the team pastes: the whole caption (Post::pasteText(), as the ZIP has it), each language alone when
     * there are several, the alt text, the first comment (Instagram and Facebook), and what is wrong with the text, if anything.
     *
     * @return array<string, array{text: string, languages: array<string, string>, alt: ?string, first_comment: string, max: int, problem: ?string}>
     */
    public static function texts(Post $post): array
    {
        $texts = [];
        foreach ($post->platforms as $platform) {
            $written = $post->variants->where('platform', $platform)->filter(fn ($v) => trim((string) $v->caption) !== '')->pluck('lang')->all();
            $languages = array_values(array_intersect($post->languages(), $written));
            $text = $post->pasteText($platform);
            $max = PostRequest::CAPTION_MAX[$platform];

            $texts[$platform] = [
                'text' => $text,
                'languages' => count($languages) > 1 ? collect($languages)->mapWithKeys(fn ($lang) => [$lang => $post->pasteText($platform, [$lang])])->all() : [],
                'alt' => $post->altTextFor($platform),
                'first_comment' => in_array($platform, ['instagram', 'facebook'], true) ? $post->firstCommentFor($platform) : '',
                'max' => $max,
                'problem' => match (true) {
                    $text === '' => __('No caption written for this platform.'),
                    (bool) preg_match('/\{\{\s*[\p{L}\p{N}_]+\s*\}\}/u', $text, $m) => __('The placeholder :placeholder is not in the client profile: fix it on the post before pasting.', ['placeholder' => $m[0]]),
                    mb_strlen($text) > $max => __('Over the limit of :max characters: shorten it on the post.', ['max' => $max]),
                    default => null,
                },
            ];
        }

        return $texts;
    }
}
