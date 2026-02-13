<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
  // Controller methods for handling user-related API requests will go here
  function index(Request $request)
  {
    $query = User::query();

    if ($request->has('search')) {
      $search = $request->input('search');
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', '%' . $search . '%')
          ->orWhere('email', 'like', '%' . $search . '%')
          ->orWhere('first_name', 'like', '%' . $search . '%')
          ->orWhere('last_name', 'like', '%' . $search . '%');
      });
    }

    return UserResource::collection($query->get());
  }

  function show(User $user)
  {
    return new UserResource($user);
  }
  function register(Request $request)
  {
    $validated = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|string|email|max:255|unique:users', 'password' => 'required|string|min:8',]);
    $user = User::create(['name' => $validated['name'], 'email' => $validated['email'], 'password' => Hash::make($validated['password']),]);
    return new UserResource($user);
  }
  function login(Request $request)
  {
    $validated = $request->validate(['email' => 'required|string|email', 'password' => 'required|string',]);
    $user = User::where('email', $validated['email'])->first();
    if (!$user || !Hash::check($validated['password'], $user->password)) {
      return response()->json(['message' => 'Invalid credentials'], 401);
    }
    $token = $user->createToken('auth_token')->plainTextToken;
    return response()->json(['user' => new UserResource($user), 'access_token' => $token, 'token_type' => 'Bearer']);
  }

  function destroy(User $user)
  {
    $user->delete();
    return response()->noContent();
  }

  function updateInfo(Request $request, User $user)
  {
    $validated = $request->validate([
      'name' => 'sometimes|required|string|max:255',
      'first_name' => 'sometimes|nullable|string|max:255',
      'last_name' => 'sometimes|nullable|string|max:255',
      'biography' => 'sometimes|nullable|string',
      'avatar_url' => 'sometimes|nullable|string|max:255',
      'is_premium' => 'sometimes|required|boolean',
      'is_expert' => 'sometimes|required|boolean',
      'premium_expire' => 'sometimes|nullable|date',
    ]);
    $user->update($validated);
    return new UserResource($user);
  }
  function updateCredentials(Request $request, User $user)
  {
    if ($request->user()->id !== $user->id) {
      return response()->json(['message' => 'Unauthorized'], 403);
    }
    $validated = $request->validate(['email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id, 'password' => 'sometimes|required|string|min:8',]);
    if (isset($validated['email'])) {
      $user->email = $validated['email'];
    }
    if (isset($validated['password'])) {
      $user->password = Hash::make($validated['password']);
    }
    $user->save();
    return new UserResource($user);
  }

  function makePremium(User $user)
  {
    $user->is_premium = true;
    $user->premium_expire = now()->addYear();
    $user->save();
    return new UserResource($user);
  }

  function revokePremium(User $user)
  {
    $user->is_premium = false;
    $user->premium_expire = null;
    $user->save();
    return new UserResource($user);
  }
}
