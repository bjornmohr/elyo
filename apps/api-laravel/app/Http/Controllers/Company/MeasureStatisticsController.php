<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\MeasureFieldStatisticsResource;
use App\Services\Insights\Contracts\MeasureStatisticsProvider;
use Illuminate\Http\Request;

class MeasureStatisticsController extends Controller
{
    public function __construct(private readonly MeasureStatisticsProvider $statisticsProvider)
    {
    }

    public function index(Request $request)
    {
        return MeasureFieldStatisticsResource::collection(
            $this->statisticsProvider->statisticsFor($request->user())
        );
    }
}
