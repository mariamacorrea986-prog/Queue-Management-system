<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StationRequest;
use App\Http\Resources\StationResource;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Students and drivers only need stations that are in use; operators and admins see everything.
        $stations = Station::query()
            ->when(! $request->user()->hasRole('operator', 'admin'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return StationResource::collection($stations);
    }

    public function store(StationRequest $request): \Illuminate\Http\JsonResponse
    {
        $station = Station::create($request->validated())->refresh();

        return (new StationResource($station))->response()->setStatusCode(201);
    }

    public function update(StationRequest $request, Station $station): StationResource
    {
        $station->update($request->validated());

        return new StationResource($station);
    }
}
