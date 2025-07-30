<?php

namespace App\Services\Email;

/**
 * Define el contrato que cualquier proveedor de servicios de correo debe seguir.
 */
interface EmailProviderInterface
{
    /**
     * Envía un correo electrónico.
     *
     * @param string $recipientEmail La dirección de correo del destinatario.
     * @param string $subject El asunto del correo.
     * @param string $htmlContent El cuerpo del correo en formato HTML.
     * @param string $fromEmail El correo del remitente.
     * @param string $fromName El nombre del remitente.
     * @param array $metadata Metadatos adicionales para el seguimiento (ej: ['log_id' => 123]).
     * @return string|null Devuelve el ID único del mensaje del proveedor si el envío fue exitoso, o null si falló.
     */
    public function send(
        string $recipientEmail,
        string $subject,
        string $htmlContent,
        string $fromEmail,
        string $fromName,
        array $metadata = []
    ): ?string;

    /**
     * Valida la firma de un webhook entrante para garantizar su autenticidad.
     *
     * @param \Illuminate\Http\Request $request La solicitud entrante del webhook.
     * @return bool Devuelve true si la firma es válida, false en caso contrario.
     */
    public function verifyWebhookSignature(\Illuminate\Http\Request $request): bool;
}