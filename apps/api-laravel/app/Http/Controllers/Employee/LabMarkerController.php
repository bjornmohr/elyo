<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\LabMarkerHistoryRequest;
use App\Http\Requests\Employee\StoreLabMarkerReadingRequest;
use App\Http\Resources\Employee\LabMarkerHistoryEntryResource;
use App\Http\Resources\Employee\LabMarkerReadingResource;
use App\Services\Health\LabMarkerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Employee lab-marker endpoints (ELYO-102 §1.1–§1.5).
 *
 * Thin by design: subject resolution, validation of the domain rules and status
 * derivation all live in `LabMarkerService`. Only the caller's own data is
 * reachable, and a foreign id is answered exactly like a missing one so reading
 * ownership cannot be probed.
 */
class LabMarkerController extends Controller
{
    public function __construct(private readonly LabMarkerService $labMarkerService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return LabMarkerReadingResource::collection(
            $this->labMarkerService->latestForUser($request->user()->id),
        );
    }

    public function history(LabMarkerHistoryRequest $request, string $markerKey): AnonymousResourceCollection|JsonResponse
    {
        try {
            $history = $this->labMarkerService->historyForUser(
                $request->user()->id,
                $markerKey,
                $request->perPage(),
            );
        } catch (ModelNotFoundException) {
            return $this->markerNotFound();
        }

        return LabMarkerHistoryEntryResource::collection($history->appends($request->query()));
    }

    public function store(StoreLabMarkerReadingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $reading = $this->labMarkerService->createReadingForUser(
                $request->user()->id,
                $validated['markerKey'],
                $validated['value'],
                $validated['measuredAt'],
            );
        } catch (ModelNotFoundException) {
            return $this->markerNotFound();
        }

        return response()->json([
            'data' => new LabMarkerReadingResource($reading),
        ], 201);
    }

    public function destroy(Request $request, string $reading): JsonResponse
    {
        $deleted = $this->labMarkerService->deleteReadingForUser($request->user()->id, $reading);

        if (! $deleted) {
            return response()->json([
                'error' => [
                    'code' => 'LAB_READING_NOT_FOUND',
                    'message' => 'Lab reading not found.',
                ],
            ], 404);
        }

        return response()->json(null, 204);
    }

    private function markerNotFound(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'LAB_MARKER_NOT_FOUND',
                'message' => 'Lab marker not found.',
            ],
        ], 404);
    }
}
