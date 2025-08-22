<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Aquí iría la lógica de permisos, por ahora lo dejamos abierto
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:sales_products,sku',
            'tax_code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'price_includes_tax' => 'required|boolean',
            'is_exempt' => 'required|boolean',
            'track_inventory' => 'required|boolean',
            'stock_quantity' => 'required_if:track_inventory,true|numeric|min:0',
            'is_active' => 'required|boolean',
            'tax_ids' => 'nullable|array', // Array de IDs de los impuestos a asociar
            'tax_ids.*' => 'exists:settings_taxes,id',
        ];
    }
}