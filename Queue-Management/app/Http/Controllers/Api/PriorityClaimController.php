<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimFilterRequest;
use App\Http\Requests\ReviewClaimRequest;
use App\Http\Requests\StorePriorityClaimRequest;
use App\Http\Resources\PriorityClaimResource;
use App\Models\PriorityClaim;
use App\Services\AuditLogger;
use App\Services\PriorityClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriorityClaimController extends Controller
{
    public function __construct(private PriorityClaimService $claims)
    {
    }

    // POST /priority-claims: the student asks for priority. The student is always the logged-in user.
    public function store(StorePriorityClaimRequest $request): JsonResponse
    {
        $claim = $this->claims->request($request->user(), $request->validated('category'), $request->file('document'), $request->ip());

        return (new PriorityClaimResource($claim))->response()->setStatusCode(201);
    }

    // GET /priority-claims/mine: a student sees only their own claims.
    public function mine(Request $request): AnonymousResourceCollection
    {
        return PriorityClaimResource::collection(
            PriorityClaim::where('user_id', $request->user()->id)->latest('id')->get()
        );
    }

    // GET /priority-claims: the review list for operators and admins.
    public function index(ClaimFilterRequest $request, AuditLogger $audit): AnonymousResourceCollection
    {
        $claims = PriorityClaim::with('user:id,name,student_id')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('user', function ($u) use ($request) {
                    $u->where('name', 'like', "%{$request->q}%")->orWhere('student_id', 'like', "%{$request->q}%");
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        // Every claim shown to this person is recorded, one audit row each (FR-23).
        $audit->logMany($request->user(), 'priority_claim.viewed', $claims->getCollection(), $request->ip());

        return PriorityClaimResource::collection($claims);
    }

    // Operators and admins may view submitted files. Each access is recorded.
    public function document(Request $request, PriorityClaim $claim): StreamedResponse
    {
        abort_unless($request->user()->hasRole('operator', 'admin'), 403);
        abort_unless($claim->document_path && Storage::disk('local')->exists($claim->document_path), 404, 'Supporting document not found.');

        app(AuditLogger::class)->log($request->user(), 'priority_claim.document_viewed', $claim, null, [], $request->ip());

        return Storage::disk('local')->download(
            $claim->document_path,
            $claim->document_original_name ?: 'priority-supporting-document'
        );
    }

    public function approve(ReviewClaimRequest $request, PriorityClaim $claim): PriorityClaimResource
    {
        $claim = $this->claims->approve(
            $claim, $request->user(), $request->validated('valid_until'), $request->validated('reason'), $request->ip()
        );

        return new PriorityClaimResource($claim->load('user:id,name,student_id'));
    }

    public function reject(ReviewClaimRequest $request, PriorityClaim $claim): PriorityClaimResource
    {
        $claim = $this->claims->reject($claim, $request->user(), $request->validated('reason'), $request->ip());

        return new PriorityClaimResource($claim->load('user:id,name,student_id'));
    }
}
