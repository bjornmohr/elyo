<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPartnerActionRequest;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPartnerController extends Controller
{
    private const TRANSITIONS = [
        'approve' => ['from' => [PartnerVerificationStatus::PENDING_REVIEW], 'to' => PartnerVerificationStatus::VERIFIED],
        'reject' => ['from' => [PartnerVerificationStatus::PENDING_REVIEW], 'to' => PartnerVerificationStatus::REJECTED],
        'suspend' => ['from' => [PartnerVerificationStatus::VERIFIED],       'to' => PartnerVerificationStatus::SUSPENDED],
        'unsuspend' => ['from' => [PartnerVerificationStatus::SUSPENDED],      'to' => PartnerVerificationStatus::VERIFIED],
    ];

    public function index(Request $request): JsonResponse
    {
        $statusParam = $request->query('status');
        $take = 50;

        $query = Partner::orderBy('verification_status', 'asc')
            ->orderBy('created_at', 'desc');

        if ($statusParam && PartnerVerificationStatus::tryFrom($statusParam)) {
            $query->where('verification_status', $statusParam);
        }

        $partners = $query->paginate($take);

        return response()->json($partners);
    }

    public function update(AdminPartnerActionRequest $request, string $id): JsonResponse
    {
        $partner = Partner::findOrFail($id);
        $action = $request->action;
        $transition = self::TRANSITIONS[$action];

        if (! in_array($partner->verification_status, $transition['from'])) {
            return response()->json(['error' => 'invalid_transition'], 400);
        }

        $partner->update([
            'verification_status' => $transition['to'],
            'reviewed_at' => now(),
            'reviewed_by_id' => $request->user()->id,
            'rejection_reason' => $action === 'reject' ? $request->rejectionReason : null,
        ]);

        // TODO: Send emails (Approved, Rejected, Suspended)

        return response()->json(['partner' => $partner]);
    }
}
