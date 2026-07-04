<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Services\Insights\Contracts\MeasureImpactProvider;
use Illuminate\Http\Request;

class MeasureImpactController extends Controller
{
    public function __construct(private readonly MeasureImpactProvider $impactProvider)
    {
    }

    public function show(Request $request, int|string $id)
    {
        $user = $request->user();

        $measure = Measure::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->impactProvider->impactFor($user, $measure),
        ]);
    }
}
