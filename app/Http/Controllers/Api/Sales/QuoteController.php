<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuoteController extends Controller
{
    protected $calculator;

    public function __construct(QuoteCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    public function index(Request $request)
    {
        // Lógica para listar y filtrar cotizaciones
        $quotes = Quote::with('contact', 'user', 'issuing_company')->latest()->paginate();
        return response()->json($quotes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:crm_contacts,id',
            'issuing_company_id' => 'required|exists:settings_issuing_companies,id',
            'valid_until' => 'nullable|date',
            'notes_customer' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.taxes' => 'nullable|array|min:1',
            'items.*.product_id' => 'nullable|exists:sales_products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $quote = new Quote([
            'contact_id' => $validated['contact_id'],
            'issuing_company_id' => $validated['issuing_company_id'],
            'user_id' => Auth::id(),
            'status' => 'borrador',
            'quote_number' => 'COT-' . time(), // Lógica de numeración simple por ahora
            'valid_until' => $validated['valid_until'] ?? null,
            'notes_customer' => $validated['notes_customer'] ?? null,
        ]);
        
        // Creamos los items en memoria para el calculador
        foreach ($validated['items'] as $itemData) {
            $quote->items->add(new \App\Models\Sales\QuoteItem($itemData));
        }

        // Usamos el servicio para calcular todos los totales
        $quote = $this->calculator->calculate($quote);
        
        // Guardamos la cotización y luego sus items
        $quote->save();
        $quote->items()->saveMany($quote->items);

        return response()->json($quote->load('items', 'contact', 'user'), 201);
    }

    public function show(Quote $quote)
    {
        return response()->json($quote->load('items', 'contact', 'user', 'issuing_company'));
    }

    public function update(Request $request, Quote $quote)
    {
        // Lógica de actualización similar a store(), recalculando totales.
        $validated = $request->validate([
            'contact_id' => 'required|exists:crm_contacts,id',
            'issuing_company_id' => 'required|exists:settings_issuing_companies,id',
            'valid_until' => 'nullable|date',
            'notes_customer' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.taxes' => 'nullable|array|min:1',
            'items.*.product_id' => 'nullable|exists:sales_products,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $quote->fill([
            'contact_id' => $validated['contact_id'],
            'issuing_company_id' => $validated['issuing_company_id'],
            'valid_until' => $validated['valid_until'] ?? null,
            'notes_customer' => $validated['notes_customer'] ?? null,
        ]);

        // Limpiamos los items existentes y los reemplazamos por los nuevos
        $quote->items()->delete();
        foreach ($validated['items'] as $itemData) {
            $quote->items->add(new \App\Models\Sales\QuoteItem($itemData));
        }

        // Usamos el servicio para calcular todos los totales
        $quote = $this->calculator->calculate($quote);

        // Guardamos la cotización y luego sus items
        $quote->save();
        $quote->items()->saveMany($quote->items);

        return response()->json($quote);
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();
        return response()->json(null, 204);
    }

    /**
     * Duplica una cotización existente.
     */
    public function duplicate(Quote $quote)
    {
        $newQuote = $quote->replicate()->fill([
            'quote_number' => 'COT-' . time(),
            'status' => 'borrador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $newQuote->save();

        foreach ($quote->items as $item) {
            $newQuote->items()->create($item->toArray());
        }

        return response()->json($newQuote->load('items'), 201);
    }

    public function updateStatus(Request $request, Quote $quote)
    {
        $validated = $request->validate(['status' => 'required|in:aceptada,rechazada,borrador']);
        $quote->status = $validated['status'];
        $quote->save();

        // Si el nuevo estado es 'aceptada', disparamos el evento para el inventario
        if ($quote->status === 'aceptada') {
            event(new \App\Events\Sales\QuoteAccepted($quote));
        }

        return response()->json($quote->fresh()->load('items'));
    }
}