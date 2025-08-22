<?php
namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Quote;
use App\Models\Sales\QuoteItem;
use App\Services\Sales\QuoteCalculatorService;
use Illuminate\Http\Request;

class QuoteCalculatorController extends Controller
{
    public function calculate(Request $request, QuoteCalculatorService $calculator)
    {
        // Validamos los datos de la cotización en memoria
        $quote = new Quote($request->all());
        foreach ($request->input('items', []) as $itemData) {
            $quote->items->add(new QuoteItem($itemData));
        }

        // Usamos el servicio para calcular los totales
        $calculatedQuote = $calculator->calculate($quote);

        // Devolvemos solo los campos calculados
        return response()->json([
            'subtotal' => $calculatedQuote->subtotal,
            'tax_details' => $calculatedQuote->tax_details,
            'grand_total' => $calculatedQuote->grand_total,
        ]);
    }
}