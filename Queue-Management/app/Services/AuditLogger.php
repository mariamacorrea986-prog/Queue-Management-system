<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// One place that writes to audit_logs, so every sensitive action is recorded the same way.
class AuditLogger
{
    public function log(
        ?User $user,
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $details = [],
        ?string $ip = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'details' => $details ?: null,
            'ip_address' => $ip,
        ]);
    }

    // One audit row per record, written with a single INSERT. Used when someone views a list
    // (for example priority claims): each claim they were shown gets its own entry.
    public function logMany(?User $user, string $action, iterable $subjects, ?string $ip = null): void
    {
        $now = now();
        $rows = [];

        foreach ($subjects as $subject) {
            $rows[] = [
                'user_id' => $user?->id,
                'action' => $action,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'reason' => null,
                'details' => null,
                'ip_address' => $ip,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            AuditLog::insert($rows);
        }
    }
}
