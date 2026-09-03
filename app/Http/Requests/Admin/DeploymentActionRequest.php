<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeploymentActionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->is_admin && ! $this->session()->has('impersonator_id'); }
    public function rules(): array { return ['password' => ['required', 'current_password'], 'allow_downgrade' => ['sometimes', 'boolean']]; }
    public function messages(): array
    {
        return [
            'password.required' => 'رمز عبور حساب مدیر را وارد کنید.',
            'password.current_password' => 'رمز عبور حساب مدیر صحیح نیست؛ کلید Deployment یا APP_KEY را وارد نکنید.',
        ];
    }
}
