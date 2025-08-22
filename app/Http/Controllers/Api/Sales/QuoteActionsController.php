<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\QuoteEmail;
use Barryvdh\DomPDF\Facade\Pdf;

class QuoteActionsController extends Controller
{
    /**
     * Genera y devuelve el PDF de una cotización.
     */
    public function viewPdf(Quote $quote)
    {
        // Cargar todas las relaciones necesarias para el PDF
        $quote->load('items', 'contact', 'issuing_company', 'user');
        
        // Generar el PDF
        $pdf = Pdf::loadView('pdfs.quote-pdf', ['quote' => $quote]);
        
        // Devolver el PDF en el navegador
        return $pdf->stream("Cotizacion-{$quote->quote_number}.pdf");
    }

    /**
     * Envía la cotización por correo electrónico.
     */
    public function sendEmail(Request $request, Quote $quote)
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'cc' => 'nullable|array',
            'cc.*' => 'sometimes|required|email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'sometimes|required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        // Cargar relaciones
        $quote->load('items', 'contact', 'issuing_company', 'user');

        // Generar el PDF como una cadena de datos
        $pdfData = Pdf::loadView('pdfs.quote-pdf', ['quote' => $quote])->output();

        // Enviar el correo
        $mail = Mail::to($validated['to']);

        if (!empty($validated['cc'])) {
            $mail->cc($validated['cc']);
        }
        if (!empty($validated['bcc'])) {
            $mail->bcc($validated['bcc']);
        }

        $mail->send(new QuoteEmail(
            $quote,
            $validated['subject'],
            $validated['body'],
            $pdfData
        ));

        return response()->json(['message' => 'Cotización enviada con éxito.']);
    }
}