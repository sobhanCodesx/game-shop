<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array { return ['current_password' => ['required', 'current_password'], 'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed']]; }
    public function messages(): array { return ['current_password.current_password' => 'رمز عبور فعلی صحیح نیست.']; }
}
