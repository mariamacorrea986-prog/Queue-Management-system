<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogFilterRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    // Read only: the audit log is append-only, so there is no create, update or delete endpoint.
    public function index(AuditLogFilterRequest $request): AnonymousResourceCollection
    {
        $logs = AuditLog::with('user:id,name,role')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action . '%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('id') // newest first
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
