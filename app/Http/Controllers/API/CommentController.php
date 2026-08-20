<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    // Comment CRUD
    public function index()
    {
        // Comments are sent with their recipes. No need for index method yet.
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $comment = Comment::create([
            'content' => $validated['content'],
            'recipe_id' => $validated['recipe_id'],
            'creator_id' => $request->user()->id,
        ]);

        return response()->json($comment, 201);
    }

    public function update(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'content' => 'sometimes|required|string',
        ]);

        $comment->update($validated);

        return response()->json($comment);
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
