<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailTemplateController extends Controller
{
    /**
     * Muestra una lista de todas las plantillas de correo.
     */
    public function index()
    {
        return EmailTemplate::orderBy('name')->get();
    }

    /**
     * Guarda una nueva plantilla en la base de datos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:crm_email_templates,name',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'active' => 'boolean',
        ]);

        $template = EmailTemplate::create($validated);
        return response()->json($template, 201);
    }

    /**
     * Muestra una plantilla específica.
     */
    public function show(EmailTemplate $emailTemplate)
    {
        return $emailTemplate;
    }

    /**
     * Actualiza una plantilla existente.
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_email_templates')->ignore($emailTemplate->id)],
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'active' => 'boolean',
        ]);

        $emailTemplate->update($validated);
        return response()->json($emailTemplate);
    }

    /**
     * Elimina una plantilla de correo.
     */
    public function destroy(EmailTemplate $emailTemplate)
    {
        // Opcional: Podrías añadir una validación aquí para no permitir eliminar
        // una plantilla si está siendo usada en alguna secuencia activa.

        $emailTemplate->delete();
        return response()->json(['message' => 'Plantilla eliminada correctamente.']);
    }
}