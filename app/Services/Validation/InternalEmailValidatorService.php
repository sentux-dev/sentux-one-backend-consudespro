<?php

namespace App\Services\Validation;

class InternalEmailValidatorService
{
    /**
     * Valida una dirección de correo usando formato y registros DNS.
     * Devuelve 'valid' si pasa ambas pruebas, o 'invalid' si falla alguna.
     *
     * @param string $email
     * @return string
     */
    public function validate(string $email): string
    {
        // Nivel 1: Validación de Formato (Regex)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalid_format';
        }

        // Nivel 2: Validación de Dominio (DNS Check)
        $domain = substr(strrchr($email, "@"), 1);
        if (!$this->domainHasMxRecords($domain)) {
            return 'invalid_domain';
        }

        return 'valid';
    }

    /**
     * Verifica si un dominio tiene registros MX.
     * Usa una caché para no repetir la misma consulta DNS para el mismo dominio.
     *
     * @param string $domain
     * @return bool
     */
    private function domainHasMxRecords(string $domain): bool
    {
        return cache()->remember("mx_records_{$domain}", now()->addHours(24), function () use ($domain) {
            return checkdnsrr($domain, 'MX');
        });
    }
}