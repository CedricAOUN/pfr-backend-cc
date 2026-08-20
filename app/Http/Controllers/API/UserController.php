<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Controller methods for handling user-related API requests will go here
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        return PublicUserResource::collection($query->get());
    }

    public function show(Request $request, User $user)
    {
        if ($request->user()->id === $user->id || $request->user()->hasRole('admin')) {
            return new UserResource($user->load('favorites'));
        }

        return new PublicUserResource($user);
    }

    public function register(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|string|email|max:255|unique:users', 'password' => 'required|string|min:8']);
        $user = User::create(['name' => $validated['name'], 'email' => $validated['email'], 'password' => Hash::make($validated['password'])]);
        $user->assignRole('regular_user');

        Mail::raw('Welcome to MealMosaic, '.$user->name.'!', function ($message) use ($user) {
            $message->to($user->email)->subject('New Account Registration');
        });

        return new UserResource($user);
    }

    public function login(Request $request)
    {
        $validated = $request->validate(['email' => 'required|string|email', 'password' => 'required|string']);
        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['user' => new UserResource($user), 'access_token' => $token, 'token_type' => 'Bearer']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Not authenticated'], 401);
        }

        return new UserResource($request->user()->load('favorites'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }

    public function updateInfo(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'name')->ignore($user->id),
            ],
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'biography' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('avatar_url')) {
            if ($user->avatar_url) {
                $oldPath = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('avatar_url')->store('user_avatars', 'public');
            $validated['avatar_url'] = Storage::url($path);
        }

        $user->update($validated);

        return new UserResource($user);
    }

    public function updateCredentials(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate(['email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id, 'password' => 'sometimes|required|string|min:8']);
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return new UserResource($user);
    }
}
