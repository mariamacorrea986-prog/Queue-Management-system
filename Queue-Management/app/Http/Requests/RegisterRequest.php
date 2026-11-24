<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // anyone may register as a student
    }

    // Note: there is deliberately no 'role' rule. Any role sent by the client is ignored.
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'student_id' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', 'unique:users,student_id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // needs password_confirmation
        ];
    }
}
