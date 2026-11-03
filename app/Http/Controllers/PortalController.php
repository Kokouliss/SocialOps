<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The client's approval page, reachable only through the review's signed link. */
class PortalController extends Controller
{
    public function show(Review $review): View
    {
        return view('portal.show', [
            'review' => $review,
            'posts' => $review->posts()->with(['variants', 'assets'])->get(),
            'name' => session('reviewer_name'),
        ]);
    }

    public function decide(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer', 'in:'.$review->pending()->pluck('posts.id')->join(',')],
            'decision' => ['required', 'in:approved,changes_requested'],
            'comment' => ['nullable', 'string', 'max:2000', 'required_if:decision,changes_requested'],
            'name' => ['required', 'string', 'max:100'],
        ], ['comment.required_if' => __('Tell us what to change.')]);

        session(['reviewer_name' => $data['name']]);
        $review->decide($review->posts()->findOrFail($data['post_id']), $data['decision'], $data['comment'] ?? null, $data['name']);

        // GET and POST share the signed URL, so the redirect keeps the signature
        return redirect($request->fullUrl())->with('status', $data['decision']);
    }

    public function asset(Review $review, Asset $asset): StreamedResponse
    {
        abort_unless($review->posts()->whereHas('assets', fn ($q) => $q->whereKey($asset->id))->exists(), 404);

        return Storage::disk($asset->disk)->response($asset->path);
    }
}
