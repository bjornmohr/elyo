<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\InviteToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount('users')->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $companies]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:companies,slug',
            'anonymity_threshold' => 'nullable|integer|min:1',
        ]);

        $company = Company::create([
            'name' => $request->name,
            'slug' => $request->slug ?? Str::slug($request->name),
            'anonymity_threshold' => $request->anonymity_threshold ?? 5,
            'created_by_elyo_admin_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $company], 201);
    }

    public function show(Company $company)
    {
        $company->loadCount('users');

        return response()->json(['data' => $company]);
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:companies,slug,'.$company->id,
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'anonymity_threshold' => 'sometimes|integer|min:1',
        ]);

        $company->update($request->only(['name', 'slug', 'status', 'anonymity_threshold']));

        return response()->json(['data' => $company]);
    }

    public function inviteCompanyAdmin(Request $request, Company $company)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if email already belongs to a different company
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser && $existingUser->company_id && $existingUser->company_id !== $company->id) {
            return response()->json([
                'error' => ['code' => 'COMPANY_CONFLICT', 'message' => 'Diese E-Mail gehört bereits zu einem anderen Unternehmen.'],
            ], 422);
        }

        $rawToken = Str::random(64);

        $invite = InviteToken::create([
            'company_id' => $company->id,
            'email' => $request->email,
            'role' => Role::COMPANY_ADMIN,
            'token_hash' => hash('sha256', $rawToken),
            'invited_by_user_id' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        // In production, send email with $rawToken. For now, return it.
        return response()->json([
            'data' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role->value,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'invite_token' => $rawToken, // DEV only
            ],
        ], 201);
    }
}
