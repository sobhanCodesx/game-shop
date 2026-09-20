<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeploymentAgentChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation_id' => ['nullable', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('deployment.max_package_size')],
            'name' => ['required', 'string', 'max:180', 'regex:/\.zip$/i'],
            'source_sha' => ['required', 'string', 'regex:/^[0-9a-f]{40}$/'],
            'source_ref' => ['required', 'string', 'in:refs/heads/main'],
            'run_id' => ['required', 'string', 'max:80', 'regex:/^[0-9]+$/'],
            'chunk' => ['required', 'file', 'max:'.ceil(config('deployment.chunk_size') / 1024)],
        ];
    }
}
