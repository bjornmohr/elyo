<?php

namespace App\Services\Invitations;

use App\Models\InviteToken;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class InviteAcceptanceService
{
    public function __construct(
        private readonly InviteTeamValidator $teamValidator,
        private readonly MappingServiceContract $mappingService,
    ) {}

    public function accept(string $token, string $name, string $password): User
    {
        $invite = InviteToken::with('company')
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invite) {
            throw new InvitationDomainException(
                'INVALID_INVITE',
                'Einladung ungültig oder abgelaufen.',
            );
        }

        if (
            $invite->team_id !== null
            && ! $this->teamValidator->inviteTeamBelongsToCompany((int) $invite->team_id, (int) $invite->company_id)
        ) {
            throw new InvitationDomainException(
                'INVALID_INVITE_TEAM',
                'Die Team-Zuordnung dieser Einladung ist ungültig.',
            );
        }

        $existingUser = User::where('email', $invite->email)->first();

        if ($existingUser) {
            if ($existingUser->company_id && $invite->company_id && $existingUser->company_id !== $invite->company_id) {
                throw new InvitationDomainException(
                    'COMPANY_CONFLICT',
                    'Dieses Konto gehört bereits zu einem anderen Unternehmen.',
                );
            }

            if (
                $invite->team_id !== null
                && $existingUser->team_id !== null
                && (int) $existingUser->team_id !== (int) $invite->team_id
            ) {
                throw new InvitationDomainException(
                    'TEAM_CONFLICT',
                    'Dieses Konto ist bereits einem anderen Team zugeordnet.',
                );
            }

            return DB::transaction(function () use ($existingUser, $invite) {
                if (! $existingUser->hasRole($invite->role)) {
                    UserRole::create([
                        'user_id' => $existingUser->id,
                        'role' => $invite->role,
                    ]);
                }

                if ($invite->team_id !== null && $existingUser->team_id === null) {
                    $existingUser->update(['team_id' => $invite->team_id]);
                }

                $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

                return $existingUser->load('roles', 'company');
            });
        }

        $user = DB::transaction(function () use ($invite, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $invite->email,
                'password' => $password,
                'company_id' => $invite->company_id,
                'team_id' => $invite->team_id,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => $invite->role,
            ]);

            $invite->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $user->load('roles', 'company');
        });

        try {
            $this->mappingService->provisionOwnSubject($user->id, PurposeCode::PROVISIONING);
        } catch (Throwable) {
            // ADR-001 §2.2 accepts retry as compensation for cross-database
            // partial failure; registration remains valid and the idempotent
            // elyo:provision-subjects sweep repairs the missing mapping.
            Log::warning('Health subject provisioning failed after invite acceptance; run elyo:provision-subjects.');
        }

        return $user;
    }
}
