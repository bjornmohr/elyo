<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Services\Invitations\InvitationDomainException;
use App\Services\Invitations\InviteAcceptanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class InviteController extends Controller
{
    public function __construct(private readonly InviteAcceptanceService $inviteAcceptanceService)
    {
    }

    public function verify(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $tokenHash = hash('sha256', $request->input('token'));

        $invite = InviteToken::with('company')
            ->where('token_hash', $tokenHash)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            return response()->json([
                'valid' => false,
                'error' => 'Einladung ungültig oder abgelaufen.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => $invite->email,
            'companyName' => $invite->company?->name,
            'role' => $invite->role->value,
            'expiresAt' => $invite->expires_at->toIso8601String(),
        ]);
    }

    public function accept(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = $this->inviteAcceptanceService->accept(
                $request->input('token'),
                $request->input('name'),
                $request->input('password'),
            );
        } catch (InvitationDomainException $exception) {
            return response()->json([
                'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()],
            ], $exception->statusCode);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->roleNames(),
                'companyId' => $user->company_id,
                'teamId' => $user->team_id,
            ],
        ]);
    }
}
