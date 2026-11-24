<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusRequest;
use App\Http\Resources\BusResource;
use App\Models\Bus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BusResource::collection(Bus::orderBy('plate_number')->get());
    }

    public function store(BusRequest $request): JsonResponse
    {
        $bus = Bus::create($request->validated())->refresh();

        return (new BusResource($bus))->response()->setStatusCode(201);
    }

    public function update(BusRequest $request, Bus $bus): BusResource
    {
        $bus->update($request->validated());

        return new BusResource($bus);
    }
}
