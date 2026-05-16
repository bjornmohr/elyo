<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\PartnerLoginRequest;
use App\Http\Requests\Partner\PartnerRegisterRequest;
use App\Models\Partner;
use App\Enums\PartnerVerificationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PartnerAuthController extends Controller
{
    public function register(PartnerRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $password = $data['password'];
        unset($data['password']);

        $partner = Partner::create(array_merge($data, [
            'password_hash' => Hash::make($password),
            'verification_status' => PartnerVerificationStatus::PENDING_DOCS,
        ]));

        $token = $partner->createToken('partner-token')->plainTextToken;

        return response()->json([
            'partnerId' => $partner->id,
            'token' => $token,
        ], 201);
    }

    public function login(PartnerLoginRequest $request): JsonResponse
    {
        $partner = Partner::where('email', $request->email)->first();

        if (!$partner || !Hash::check($request->password, $partner->password_hash)) {
            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        if (in_array($partner->verification_status, [PartnerVerificationStatus::REJECTED, PartnerVerificationStatus::SUSPENDED])) {
            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        $token = $partner->createToken('partner-token')->plainTextToken;

        return response()->json([
            'partnerId' => $partner->id,
            'status' => $partner->verification_status,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
