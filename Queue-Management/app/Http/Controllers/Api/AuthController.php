<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // validated() returns only the fields in the rules, so a 'role' sent by the client never gets here.
        // role is not fillable, so the database default ('student') always applies.
        $user = User::create($request->validated());

        return response()->json([
            'user' => new UserResource($user->refresh()), // refresh() loads the default role from the database
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        // One message for "no such email" and "wrong password", so attackers cannot learn which emails exist.
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Delete only the token used for this request, so other devices stay logged in.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function consent(Request $request): UserResource
    {
        $user = $request->user();

        // Keep the first acceptance time: accepting twice must not overwrite the original record.
        if ($user->consent_given_at === null) {
            $user->consent_given_at = now();
            $user->save();
        }

        return new UserResource($user);
    }
}
