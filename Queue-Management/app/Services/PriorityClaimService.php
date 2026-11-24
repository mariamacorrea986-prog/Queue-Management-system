<?php

namespace App\Services;

use App\Models\PriorityClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Sensitive supporting documents are stored on Laravel's private local disk, never in public storage.
class PriorityClaimService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function request(User $student, string $category, UploadedFile $document, ?string $ip): PriorityClaim
    {
        $path = $document->store('priority-claims', 'local');
        if (! $path) {
            abort(500, 'The supporting document could not be stored securely. Please try again.');
        }

        try {
            return DB::transaction(function () use ($student, $category, $document, $path, $ip) {
                // Lock the student's own row so two requests sent at the same moment cannot both pass the checks below.
                User::lockForUpdate()->findOrFail($student->id);

                $open = PriorityClaim::where('user_id', $student->id)->where('status', 'pending')->exists();
                if ($open) {
                    abort(409, 'You already have a priority request waiting for review.');
                }

                $hasStatus = PriorityClaim::where('user_id', $student->id)
                    ->where('status', 'approved')
                    ->where(function ($q) {
                        $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString());
                    })
                    ->exists();
                if ($hasStatus) {
                    abort(409, 'You already have approved priority status.');
                }

                $claim = PriorityClaim::create([
                    'user_id' => $student->id,
                    'category' => $category,
                    'document_path' => $path,
                    'document_original_name' => $document->getClientOriginalName(),
                    'document_mime_type' => $document->getMimeType(),
                    'document_size' => $document->getSize(),
                ])->refresh();

                $this->audit->log($student, 'priority_claim.requested', $claim, null, [], $ip);


                return $claim;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function approve(PriorityClaim $claim, User $operator, ?string $validUntil, ?string $reason, ?string $ip): PriorityClaim
    {
        return $this->decide($claim, 'approved', 'priority_claim.approved', $operator, $validUntil, $reason, $ip);
    }

    public function reject(PriorityClaim $claim, User $operator, ?string $reason, ?string $ip): PriorityClaim
    {
        return $this->decide($claim, 'rejected', 'priority_claim.rejected', $operator, null, $reason, $ip);
    }

    // Approve and reject are the same steps with a different outcome, so they share this method.
    private function decide(PriorityClaim $claim, string $status, string $action, User $operator, ?string $validUntil, ?string $reason, ?string $ip): PriorityClaim
    {
        return DB::transaction(function () use ($claim, $status, $action, $operator, $validUntil, $reason, $ip) {
            // Lock the claim: if two admins decide at the same moment, the second one sees it is no longer pending.
            $claim = PriorityClaim::lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== 'pending') {
                abort(409, "This request has already been decided (it is {$claim->status}).");
            }

            // forceFill: status and reviewer fields are not mass-assignable, so a request body can never set them.
            $claim->forceFill([
                'status' => $status,
                'valid_until' => $validUntil,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
            ])->save();

            // The category is deliberately not copied into the log. The log says who decided what, and when.
            $this->audit->log($operator, $action, $claim, $reason, ['valid_until' => $validUntil], $ip);

            return $claim;
        });
    }

    // Daily job: an approved claim whose valid_until date has passed becomes "expired".
    // Check-in already ignores such a claim, so this keeps the stored status honest.
    public function expireDue(): int
    {
        $due = PriorityClaim::where('status', 'approved')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', today()->toDateString())
            ->get();

        foreach ($due as $claim) {
            DB::transaction(function () use ($claim) {
                $claim->forceFill(['status' => 'expired'])->save();
                $this->audit->log(null, 'priority_claim.expired', $claim); // no user: done by the system
            });
        }

        return $due->count();
    }
}
