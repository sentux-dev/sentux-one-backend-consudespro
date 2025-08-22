<?php

namespace App\Services\Sales;

use App\Models\Sales\Quote;
use App\Models\Sales\QuoteItem;
use App\Models\Settings\Tax; // ✅ 1. Importar el modelo Tax

class QuoteCalculatorService
{
    /**
     * Recalcula todos los totales de una cotización de forma integral.
     */
    public function calculate(Quote $quote): Quote
    {
        $subtotal = 0;
        $totalTaxableBase = 0;

        // --- FASE 1: CÁLCULO A NIVEL DE LÍNEA DE ÍTEM ---
        foreach ($quote->items as $item) {
            $basePrice = $item->unit_price;

            if ($item->price_includes_tax && !$item->is_exempt) {
                // Para este cálculo, los datos del payload son suficientes
                $itemTotalTaxRate = collect($item->taxes)->where('type', 'iva')->sum('rate') / 100;
                if ($itemTotalTaxRate > -1) { // Evitar división por cero
                    $basePrice = $item->unit_price / (1 + $itemTotalTaxRate);
                }
            }

            $itemBaseTotal = $basePrice * $item->quantity;
            $itemDiscountAmount = $this->calculateAmount($itemBaseTotal, $item->discount_type, $item->discount_value);
            $item->line_total = $itemBaseTotal - $itemDiscountAmount;
            
            $subtotal += $item->line_total;

            if (!$item->is_exempt) {
                $totalTaxableBase += $item->line_total;
            }
        }
        $quote->subtotal = $subtotal;

        // --- FASE 2: CÁLCULO A NIVEL DE COTIZACIÓN ---
        $quoteDiscountAmount = $this->calculateAmount($quote->subtotal, $quote->discount_type, $quote->discount_value);
        $totalAfterDiscount = $quote->subtotal - $quoteDiscountAmount;
        
        $taxableBaseForQuote = ($quote->subtotal > 0) 
            ? $totalTaxableBase * ($totalAfterDiscount / $quote->subtotal) 
            : 0;

        foreach ($quote->fees as $fee) {
            if ($fee->is_taxable) {
                $taxableBaseForQuote += $this->calculateAmount($quote->subtotal, $fee->type, $fee->value);
            }
        }

        // --- FASE 3: CÁLCULO DE IMPUESTOS (LÓGICA CORREGIDA) ---
        $taxDetails = [];
        $totalTaxesAndRetentions = 0;
        
        // ✅ 2. Recolectamos todos los IDs de los impuestos de todos los ítems
        $taxIds = $quote->items->pluck('taxes.*.id')->flatten()->unique()->filter()->toArray();
        
        if (!empty($taxIds)) {
            // ✅ 3. Cargamos los modelos de Tax desde la base de datos una sola vez
            $taxesToApply = Tax::findMany($taxIds);

            foreach ($taxesToApply as $tax) {
                // Ahora estamos 100% seguros de que $tax es un objeto Eloquent
                $taxAmount = $this->calculateAmount($taxableBaseForQuote, $tax->calculation_type, $tax->rate);
                
                if ($tax->type === 'retencion') {
                    $taxAmount = -$taxAmount;
                }
                
                $taxDetails[] = ['id' => $tax->id, 'name' => $tax->name, 'rate' => $tax->rate, 'amount' => $taxAmount];
                $totalTaxesAndRetentions += $taxAmount;
            }
        }
        $quote->tax_details = $taxDetails;
        
        // --- FASE 4: CÁLCULO DEL GRAN TOTAL ---
        $totalNonTaxableFees = 0;
        foreach ($quote->fees as $fee) {
            if (!$fee->is_taxable) {
                $totalNonTaxableFees += $this->calculateAmount($quote->subtotal, $fee->type, $fee->value);
            }
        }

        $quote->grand_total = $totalAfterDiscount + $totalTaxesAndRetentions + $totalNonTaxableFees;

        return $quote;
    }
    
    /**
     * Función de ayuda universal para calcular un monto.
     */
    private function calculateAmount(?float $base, ?string $type, ?float $value): float
    {
        if ($base === null || $type === null || $value === null || $value <= 0) {
            return 0;
        }

        if ($type === 'percentage') {
            return ($base * $value) / 100;
        }
        if ($type === 'fixed') {
            return $value;
        }
        return 0;
    }
}