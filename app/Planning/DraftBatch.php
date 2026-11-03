<?php

namespace App\Planning;

use App\Drafting\CaptionDrafter;

/** Research (unless a brief is supplied), plan, then caption every post. Nothing here publishes or approves. */
class DraftBatch
{
    public function __construct(
        private TrendResearcher $research,
        private ContentPlanner $planner,
        private CaptionDrafter $captions,
    ) {}

    public function run(PlanRequest $request, ?string $brief = null, ?callable $progress = null): array
    {
        $progress ??= fn () => null;

        if ($brief === null) {
            $progress('Researching trends');
            $brief = $this->research->brief($request);
        }

        $progress('Planning posts');
        ['posts' => $posts, 'warnings' => $warnings] = $this->planner->plan($request, $brief);

        $profile = $request->profile;
        foreach ($posts as $i => &$post) {
            $progress('Drafting captions '.($i + 1).'/'.count($posts));
            $post['name'] = PostName::for($profile, $post);
            $post['captions'] = $this->captions->draft($profile, [
                'platforms' => $post['platforms'],
                'format' => $post['format'],
                'pillar' => $post['pillar'],
                'brief' => $post['angle']."\n".$post['caption_brief']
                    .($post['trend_signal'] ? "\nTrend hook: {$post['trend_signal']}. {$post['why_now']}" : ''),
                'asset_description' => $post['asset_brief'],
            ]);
        }

        return [
            'client' => $profile['slug'],
            'created_at' => now()->toIso8601String(),
            'window' => ['start' => $request->from->toDateString(), 'end' => $request->to->toDateString()],
            'platforms' => $request->platforms,
            'trend_brief' => $brief,
            'warnings' => $warnings,
            'posts' => $posts,
        ];
    }
}
