<?php

namespace App\Services\Email;

use Illuminate\Support\Manager;
use App\Services\Email\Providers\MandrillService;

class EmailProviderManager extends Manager
{
    /**
     * Obtiene el proveedor de correo electrónico por defecto.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('services.default_email_provider', 'mandrill');
    }

    /**
     * Crea una instancia del driver de Mandrill.
     */
    protected function createMandrillDriver(): MandrillService
    {
        // 🔹 --- LÍNEA CORREGIDA --- 🔹
        // En lugar de "new MandrillService()", le pedimos al contenedor de Laravel
        // que lo construya por nosotros, para que inyecte sus dependencias.
        return $this->container->make(MandrillService::class);
    }

    /**
     * (Futuro) Crea una instancia del driver de Brevo.
     */
    // protected function createBrevoDriver(): BrevoService
    // {
    //     return new BrevoService();
    // }
}