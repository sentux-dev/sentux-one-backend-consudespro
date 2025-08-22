<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Product;
use App\Http\Requests\Sales\StoreProductRequest;
use App\Http\Requests\Sales\UpdateProductRequest;
use Illuminate\Http\Request; // ✅ Importar Request
use Illuminate\Support\Arr;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('taxes');

        // Búsqueda global
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Ordenación
        if ($sortField = $request->query('sortField')) {
            $sortOrder = $request->query('sortOrder', 'asc');
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->latest(); // Orden por defecto
        }
        
        // Paginación
        $perPage = $request->query('per_page', 10);
        return $query->paginate($perPage);
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        $taxIds = Arr::pull($validated, 'tax_ids');
        
        $product = Product::create($validated);
        
        if (is_array($taxIds)) {
            $product->taxes()->sync($taxIds);
        }

        return response()->json($product->load('taxes'), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('taxes'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validated = $request->validated();
        $taxIds = Arr::pull($validated, 'tax_ids');

        $product->update($validated);
                
        if ($request->has('tax_ids')) {
            $product->taxes()->sync($taxIds ?? []);
        }

        return response()->json($product->load('taxes'));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->noContent();
    }
}