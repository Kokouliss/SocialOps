{{-- The buttons and the social links: the body of the public bio page and the content of the page on the client's WordPress site, so it carries its own style --}}
<div class="socialops-bio">
    <style>.socialops-bio{max-width:420px;margin:0 auto}.socialops-bio .btn{display:block;padding:16px;margin:12px 0;border-radius:14px;background:#1f2937;color:#fff;text-decoration:none;text-align:center;font-weight:600;font-size:1.05rem}.socialops-bio nav{display:flex;justify-content:center;gap:20px;margin-top:28px}.socialops-bio nav a{color:inherit;text-decoration:none;font-weight:600;opacity:.8}</style>
    @foreach ($links as $link)
        @if ($link['kind'] !== 'social')
            <a class="btn" href="{{ $link['url'] }}" @if ($link['kind'] === 'link') target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
        @endif
    @endforeach
    <nav>
        @foreach ($links as $link)
            @if ($link['kind'] === 'social')
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
            @endif
        @endforeach
    </nav>
</div>
