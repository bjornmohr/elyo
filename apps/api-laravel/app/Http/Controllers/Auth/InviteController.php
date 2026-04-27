<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Models\Company;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InviteController extends Controller
{
    public function verify($token)
    {
        $invite = InviteToken::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            return response()->json(['message' => 'Invalid or expired invite token'], 404);
        }

        return response()->json([
            'email' => $invite->email,
            'companyName' => $invite->company->name,
            'role' => $invite->role,
        ]);
    }

    public function accept(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => ['required', Password::defaults()],
        ]);

        $invite = InviteToken::where('token', $request->token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Create the user
        $user = User::create([
            'id' => Str::orderedUuid()->toString(),
            'email' => $invite->email ?? $request->email, // email might be in token or request
            'name' => $request->name,
            'role' => $invite->role,
            'password_hash' => Hash::make($request->password),
            'company_id' => $invite->company_id,
            'team_id' => $invite->team_id,
            'is_active' => true,
        ]);

        $invite->update(['used_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
