<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeploymentChunkRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->is_admin && ! $this->session()->has('impersonator_id'); }
    public function rules(): array
    {
        return [
            'operation_id' => ['nullable', 'uuid'], 'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'], 'size' => ['required', 'integer', 'min:1', 'max:'.config('deployment.max_package_size')],
            'name' => ['required', 'string', 'max:180', 'regex:/\.zip$/i'], 'chunk' => ['required', 'file', 'max:'.ceil(config('deployment.chunk_size') / 1024)],
        ];
    }
}
