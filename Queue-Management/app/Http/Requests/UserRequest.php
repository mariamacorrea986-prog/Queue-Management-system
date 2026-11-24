<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

// Used for create (POST) and update (PUT). Admins create staff accounts; students register themselves.
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:admin middleware protects the route
    }

    public function rules(): array
    {
        $user = $this->route('user'); // null when creating
        $isUpdate = $user !== null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            // Creating: staff roles only. Updating: optional, and the checks below stop student/staff swaps.
            'role' => $isUpdate
                ? ['sometimes', Rule::in(['student', 'driver', 'operator', 'admin'])]
                : ['required', Rule::in(['driver', 'operator', 'admin'])],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8'], // empty on update = keep the old one
            'is_active' => ['sometimes', 'boolean'],
            'student_id' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('users', 'student_id')->ignore($user)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            if ($validator->errors()->isNotEmpty() || $target === null) {
                return;
            }

            $actor = $this->user();
            $deactivating = $this->has('is_active') && ! $this->boolean('is_active');
            $changingRole = $this->filled('role') && $this->input('role') !== $target->role;

            // A student's bookings and claims belong to the student role: no swapping between student and staff.
            if ($changingRole && ($target->role === 'student' || $this->input('role') === 'student')) {
                $validator->errors()->add('role', 'A student account cannot become staff, and a staff account cannot become a student.');
            }

            // Only students have a student ID.
            if ($this->filled('student_id') && $target->role !== 'student') {
                $validator->errors()->add('student_id', 'Only student accounts have a student ID.');
            }

            // Safety: an admin must not lock themselves out.
            if ($target->id === $actor->id && ($deactivating || $changingRole)) {
                $validator->errors()->add($deactivating ? 'is_active' : 'role', 'You cannot deactivate your own account or change your own role.');
                return;
            }

            // Safety: the system must always keep at least one active admin.
            if ($target->role === 'admin' && $target->is_active && ($deactivating || $changingRole)) {
                $otherAdmin = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $target->id)->exists();
                if (! $otherAdmin) {
                    $validator->errors()->add($deactivating ? 'is_active' : 'role', 'There must always be at least one active admin.');
                }
            }
        });
    }
}
