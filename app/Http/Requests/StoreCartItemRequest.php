<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'exists:products,id'], 'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:10']];
    }
}
