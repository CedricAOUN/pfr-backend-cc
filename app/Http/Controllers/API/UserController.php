<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly GoogleIdTokenVerifier $googleIdTokenVerifier) {}

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
        $authenticatedUser = $request->user('sanctum');

        if (
            $authenticatedUser &&
            ($authenticatedUser->is($user) || $authenticatedUser->hasRole('admin'))
        ) {
            return new UserResource($user->load('favorites'));
        }

        return new PublicUserResource($user);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:20|unique:users,name',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
        $user = User::create(['name' => $validated['name'], 'email' => $validated['email'], 'password' => Hash::make($validated['password'])]);
        $user->assignRole('regular_user');

        Mail::raw('Welcome to MealMosaic, '.$user->name.'!', function ($message) use ($user) {
            $message->to($user->email)->subject('New Account Registration');
        });

        return $this->authenticatedResponse($user);
    }

    public function login(Request $request)
    {
        $validated = $request->validate(['email' => 'required|string|email', 'password' => 'required|string']);
        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $this->authenticatedResponse($user);
    }

    public function google(Request $request)
    {
        $validated = $request->validate([
            'credential' => 'required|string|max:10000',
            'password' => 'sometimes|string',
        ]);

        $payload = $this->googleIdTokenVerifier->verify($validated['credential']);

        if (! $payload
            || empty($payload['sub'])
            || empty($payload['email'])
            || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false
            || filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL) !== true
        ) {
            return response()->json(['message' => 'Invalid Google credential'], 401);
        }

        $googleId = (string) $payload['sub'];
        $email = Str::lower((string) $payload['email']);
        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            return $this->authenticatedResponse($user);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            if ($user->google_id && $user->google_id !== $googleId) {
                return response()->json([
                    'message' => 'This email is already linked to another Google account.',
                    'code' => 'google_account_conflict',
                ], 409);
            }

            if (! array_key_exists('password', $validated)) {
                return response()->json([
                    'message' => 'Enter your existing password to link Google sign-in.',
                    'code' => 'account_link_required',
                ], 409);
            }

            if (! $user->password || ! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'message' => 'The password for this account is incorrect.',
                    'code' => 'invalid_link_password',
                ], 401);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $this->authenticatedResponse($user);
        }

        [$user, $wasCreated] = $this->createGoogleUser($payload, $googleId, $email);

        if ($wasCreated) {
            Mail::raw('Welcome to MealMosaic, '.$user->name.'!', function ($message) use ($user) {
                $message->to($user->email)->subject('New Account Registration');
            });
        }

        return $this->authenticatedResponse($user);
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

    public function listChefs(Request $request)
    {
        $query = User::role('chef'); // spatie/laravel-permission scope

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        $chefs = $query->get();

        return PublicUserResource::collection($chefs);
    }

    private function authenticatedResponse(User $user)
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    private function uniqueUsername(array $payload, string $email): string
    {
        $parts = array_filter([
            $this->optionalString($payload['given_name'] ?? null),
            $this->optionalString($payload['family_name'] ?? null),
        ]);
        $source = $parts
            ? implode(' ', $parts)
            : ($this->optionalString($payload['name'] ?? null) ?: Str::before($email, '@'));
        $base = Str::limit(Str::slug($source, '.') ?: 'user', 20, '');
        $candidate = $base;
        $suffix = 2;

        while (User::where('name', $candidate)->exists()) {
            $ending = '.'.$suffix++;
            $candidate = Str::limit($base, 20 - strlen($ending), '').$ending;
        }

        return $candidate;
    }

    private function createGoogleUser(array $payload, string $googleId, string $email): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $user = DB::transaction(function () use ($payload, $googleId, $email) {
                    $user = User::create([
                        'name' => $this->uniqueUsername($payload, $email),
                        'email' => $email,
                        'password' => null,
                        'google_id' => $googleId,
                        'email_verified_at' => now(),
                        'first_name' => $this->optionalString($payload['given_name'] ?? null),
                        'last_name' => $this->optionalString($payload['family_name'] ?? null),
                        'avatar_url' => $this->optionalString($payload['picture'] ?? null),
                    ]);
                    $user->assignRole('regular_user');

                    return $user;
                });

                return [$user, true];
            } catch (QueryException $exception) {
                $existing = User::where('google_id', $googleId)->first();

                if ($existing) {
                    return [$existing, false];
                }

                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to create Google user.');
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
