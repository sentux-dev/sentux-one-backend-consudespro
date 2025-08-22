<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @method \Illuminate\Routing\Route|null route($param = null)
 */

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('sales_products', 'sku')->ignore($productId)],
            'tax_code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'price_includes_tax' => 'sometimes|required|boolean',
            'is_exempt' => 'sometimes|required|boolean',
            'track_inventory' => 'sometimes|required|boolean',
            'stock_quantity' => 'sometimes|required_if:track_inventory,true|numeric|min:0',
            'is_active' => 'sometimes|required|boolean',
            'tax_ids' => 'nullable|array',
            'tax_ids.*' => 'exists:settings_taxes,id',
        ];
    }
}