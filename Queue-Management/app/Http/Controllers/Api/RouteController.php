<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\Route;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RouteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routes = Route::with(['origin', 'destination'])
            ->when(! $request->user()->hasRole('operator', 'admin'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return RouteResource::collection($routes);
    }

    public function store(RouteRequest $request): JsonResponse
    {
        $route = Route::create($request->validated())->refresh()->load(['origin', 'destination']);

        return (new RouteResource($route))->response()->setStatusCode(201);
    }

    public function update(RouteRequest $request, Route $route): RouteResource
    {
        $route->update($request->validated());

        return new RouteResource($route->load(['origin', 'destination']));
    }
}
