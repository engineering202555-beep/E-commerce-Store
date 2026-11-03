<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class AuthWaiterController extends Controller
{
    public function loginWaiter(Request $request)
    {
        $loginData = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $loginData['email'])->first();

        if (!$user || !Hash::check($loginData['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => ['Could not login with provided credentials.']
            ], 422);
        }

        if (!$user->hasRole('waiter')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only waiters can login here'
            ], 403);
        }
        $token = $user->createToken('Access Token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'role' => $user->getRoleNames(),
            'id' => $user->id,
            'access_token' => $token
        ],200);
    }

    public function logoutWaiter(){

        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        else
        {
            Auth::user()->currentAccessToken()->delete();
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ],200);
        }

    }
}
