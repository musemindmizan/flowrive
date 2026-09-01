<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A single toggle: one row per person per emoji, so "did I react" is an
 * existence check and the count is a COUNT.
 */
class CommentReactionController extends Controller
{
    private const EMOJI = '👍';

    public function __invoke(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorize('contribute', $comment->task->project);

        $existing = $comment->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', self::EMOJI);

        if ($existing->exists()) {
            $existing->delete();
        } else {
            $comment->reactions()->create([
                'user_id' => $request->user()->id,
                'emoji' => self::EMOJI,
            ]);
        }

        return back();
    }
}
