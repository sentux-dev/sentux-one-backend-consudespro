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
        return new MandrillService();
    }

    /**
     * (Futuro) Crea una instancia del driver de Brevo.
     */
    // protected function createBrevoDriver(): BrevoService
    // {
    //     return new BrevoService();
    // }
}