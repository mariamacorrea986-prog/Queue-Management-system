<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function summary(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->summary($request->fromDate(), $request->toDate(), $request->routeId())]);
    }

    public function hourly(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->hourly($request->fromDate(), $request->toDate(), $request->routeId())]);
    }

    public function routes(ReportRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reports->byRoute($request->fromDate(), $request->toDate(), $request->routeId())]);
    }
}
