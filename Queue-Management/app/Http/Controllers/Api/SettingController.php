<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::orderBy('name')->get());
    }

    // PUT /settings with only the settings to change; returns the full list again.
    public function update(UpdateSettingsRequest $request, SettingService $settings): AnonymousResourceCollection
    {
        $settings->update($request->validated(), $request->user(), $request->ip());

        return $this->index();
    }
}
