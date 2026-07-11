<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    // Comment CRUD
    function index()
    {
        // Comments are sent with their recipes. No need for index method yet.
    }

    function store(Request $request)
    {
        $user = auth('sanctum')->user();
        $request->validate([
            'content' => 'required|string',
            'recipe_id' => 'required|exists:recipes,id',
        ]);

        $request->merge(['creator_id' => $user->id]);

        $comment = Comment::create($request->all());

        return response()->json($comment, 201);
    }

    function update(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        $comment = Comment::findOrFail($id);

        if ($comment->creator_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'content' => 'sometimes|required|string',
            'creator_id' => 'sometimes|required|exists:users,id',
            'recipe_id' => 'sometimes|required|exists:recipes,id',
        ]);

        $comment->update($request->all());

        return response()->json($comment);
    }

    function destroy($id)
    {
        $user = auth('sanctum')->user();
        $comment = Comment::findOrFail($id);

        if ($comment->creator_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json(null, 204);
    }
}
