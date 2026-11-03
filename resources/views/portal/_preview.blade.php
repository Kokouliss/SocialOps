{{-- A lookalike of the post on one network: enough for the client to judge image, text and hashtags. --}}
@php($asset = $post->assets->first())
@php($vertical = in_array($post->format, ['reel', 'story', 'tiktok_video', 'tiktok_photos']))
@php($labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'google_business' => 'Google Business Profile'])
<div class="rounded-lg border border-gray-200 overflow-hidden text-sm {{ $vertical ? 'bg-black text-white' : 'bg-white' }}">
    <div class="flex items-center gap-2 px-3 py-2">
        <span class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center text-xs font-bold text-gray-700">{{ mb_substr($review->client->name, 0, 1) }}</span>
        <span class="font-semibold">{{ $review->client->name }}</span>
        <span class="ml-auto text-xs opacity-60">{{ $labels[$platform] }}</span>
    </div>
    @foreach ($post->variants->where('platform', $platform) as $variant)
        @if ($platform === 'facebook' || $platform === 'google_business')
            <div class="px-3 pb-2 whitespace-pre-line"><span class="text-xs uppercase opacity-60 mr-1">{{ $variant->lang }}</span>{{ $variant->caption }}</div>
        @endif
    @endforeach
    @if (($platform === 'facebook' || $platform === 'google_business') && $post->signatureFor($platform))
        <div class="px-3 pb-2 whitespace-pre-line opacity-80">{{ $post->signatureFor($platform) }}</div>
    @endif
    <div class="bg-gray-100 {{ $vertical ? 'aspect-[9/16]' : ($platform === 'google_business' ? 'aspect-video' : 'aspect-square') }} flex items-center justify-center overflow-hidden">
        @if (! $asset)
            <span class="text-xs text-gray-500">{{ __('Photo or video to follow') }}</span>
        @elseif ($asset->kind === 'video')
            <video src="{{ $review->assetUrl($asset) }}" controls playsinline class="h-full w-full object-cover"></video>
        @else
            <img src="{{ $review->assetUrl($asset) }}" alt="" class="h-full w-full object-cover" />
        @endif
    </div>
    @if ($platform === 'google_business')
        <div class="px-3 py-2 text-xs font-semibold text-blue-700">{{ __(match ($post->format) { 'gbp_offer' => 'View offer', 'gbp_event' => 'Learn more', default => 'Learn more' }) }}</div>
    @endif
    @foreach ($post->variants->where('platform', $platform) as $variant)
        @if ($platform === 'instagram' || $platform === 'tiktok')
            <div class="px-3 py-2 whitespace-pre-line"><span class="text-xs uppercase opacity-60 mr-1">{{ $variant->lang }}</span><span class="font-semibold mr-1">{{ $review->client->name }}</span>{{ $variant->caption }}
                @if ($loop->last && $post->signatureFor($platform)) <span class="block opacity-80">{{ $post->signatureFor($platform) }}</span> @endif
                @if ($variant->hashtags) <span class="{{ $vertical ? 'text-blue-300' : 'text-blue-800' }}">{{ collect($variant->hashtags)->map(fn ($h) => '#'.ltrim($h, '#'))->join(' ') }}</span> @endif
            </div>
        @elseif ($variant->hashtags)
            <div class="px-3 pb-2 text-blue-800">{{ collect($variant->hashtags)->map(fn ($h) => '#'.ltrim($h, '#'))->join(' ') }}</div>
        @endif
    @endforeach
</div>
