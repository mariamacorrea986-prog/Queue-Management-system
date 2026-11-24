<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

// Accounts are never deleted (history is kept): they are deactivated instead.
class UserService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function create(array $data, User $actor, ?string $ip): User
    {
        return DB::transaction(function () use ($data, $actor, $ip) {
            $user = new User();
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the model's cast
                'is_active' => $data['is_active'] ?? true,
            ]);
            $user->role = $data['role']; // role is not mass-assignable, so it is set explicitly here
            $user->save();

            $this->audit->log($actor, 'user.created', $user, null, ['role' => $user->role, 'email' => $user->email], $ip);

            return $user->refresh();
        });
    }

    public function update(User $user, array $data, User $actor, ?string $ip): User
    {
        return DB::transaction(function () use ($user, $data, $actor, $ip) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $fields = ['name', 'email', 'student_id', 'role', 'is_active'];
            $before = $user->only($fields);

            $user->fill(Arr::only($data, ['name', 'email', 'student_id', 'is_active']));
            if (isset($data['role'])) {
                $user->role = $data['role'];
            }
            $passwordChanged = ! empty($data['password']);
            if ($passwordChanged) {
                $user->password = $data['password'];
            }

            $changes = [];
            foreach ($before as $field => $old) {
                if ($user->{$field} !== $old) {
                    $changes[$field] = ['from' => $old, 'to' => $user->{$field}];
                }
            }

            $user->save();

            // A deactivated person is logged out everywhere straight away.
            if (isset($changes['is_active']) && $user->is_active === false) {
                $user->tokens()->delete();
            }

            if ($changes !== [] || $passwordChanged) {
                // The password itself is never written to the log, only the fact that it changed.
                $this->audit->log($actor, 'user.updated', $user, null, [
                    'changes' => $changes,
                    'password_changed' => $passwordChanged,
                ], $ip);
            }

            return $user;
        });
    }
}
