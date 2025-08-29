<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmailAccount;
use Webklex\PHPIMAP\ClientManager;
use Exception;

class EmailAccountService
{
    public function testConnection(array $credentials): array
    {
        try {
            $this->testSmtpConnection($credentials);
            $this->testImapConnection($credentials);
            return ['success' => true, 'message' => '¡Conexión exitosa con IMAP y SMTP!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createOrUpdateAccount(User $user, array $data): UserEmailAccount
    {
        return UserEmailAccount::updateOrCreate(
            ['user_id' => $user->id, 'email_address' => $data['email_address']],
            $data
        );
    }

    private function testSmtpConnection(array $credentials): void
    {
        $host       = (string) ($credentials['smtp_host'] ?? '');
        $port       = (int)    ($credentials['smtp_port'] ?? 0);
        $encryption = strtolower((string) ($credentials['smtp_encryption'] ?? 'ssl'));
        $username   = (string) ($credentials['smtp_username'] ?? '');
        $password   = (string) ($credentials['password'] ?? '');
        $timeout    = 10;

        if (!$host || !$port)          throw new Exception('SMTP host/port inválidos.');
        if (!$username || !$password)  throw new Exception('SMTP username o password faltantes.');

        $remote = ($encryption === 'ssl') ? "ssl://{$host}:{$port}" : "{$host}:{$port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            throw new Exception("No se pudo conectar a SMTP ($host:$port) [$errno]: $errstr");
        }

        try {
            // ✅ Consumir TODO el banner multi-línea (220- ... 220 )
            $this->smtpExpect($fp, 220, true);

            // EHLO con dominio del email si se puede
            $ehloName = $this->guessEhloName($credentials['smtp_username'] ?? $username);
            $this->smtpWrite($fp, "EHLO {$ehloName}");
            $this->smtpExpect($fp, 250, true); // multi-línea de capacidades

            if ($encryption === 'tls') {
                $this->smtpWrite($fp, "STARTTLS");
                $this->smtpExpect($fp, 220);

                $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoOk !== true) {
                    throw new Exception('Fallo al iniciar STARTTLS.');
                }

                // Re-EHLO después de STARTTLS
                $this->smtpWrite($fp, "EHLO {$ehloName}");
                $this->smtpExpect($fp, 250, true);
            }

            // AUTH LOGIN
            $this->smtpWrite($fp, "AUTH LOGIN");
            $this->smtpExpect($fp, 334);
            $this->smtpWrite($fp, base64_encode($username));
            $this->smtpExpect($fp, 334);
            $this->smtpWrite($fp, base64_encode($password));
            $this->smtpExpect($fp, 235);

            $this->smtpWrite($fp, "QUIT");
        } catch (Exception $e) {
            fclose($fp);
            throw new Exception("Error al conectar con el servidor SMTP: " . $e->getMessage());
        }

        fclose($fp);
    }

    private function testImapConnection(array $credentials): void
    {
        $imapHost       = (string) ($credentials['imap_host'] ?? '');
        $imapPort       = (int)    ($credentials['imap_port'] ?? 0);
        $imapEncryption = (string) ($credentials['imap_encryption'] ?? 'ssl');
        $imapUsername   = (string) ($credentials['imap_username'] ?? '');
        $password       = (string) ($credentials['password'] ?? '');

        if (!$imapHost || !$imapPort)         throw new Exception('IMAP host/port inválidos.');
        if (!$imapUsername || !$password)      throw new Exception('IMAP username o password faltantes.');

        $config = [
            'host'          => $imapHost,
            'port'          => $imapPort,
            'encryption'    => $imapEncryption ?: null,
            'validate_cert' => true,
            'username'      => $imapUsername,
            'password'      => $password,
            'protocol'      => 'imap',
            'timeout'       => 10,
        ];

        try {
            $clientManager = new ClientManager();
            $client = $clientManager->make($config);
            $client->connect();
            $client->disconnect();
        } catch (Exception $e) {
            throw new Exception("Error al conectar con el servidor IMAP: " . $e->getMessage());
        }
    }

    private function smtpWrite($fp, string $command): void
    {
        $written = @fwrite($fp, $command . "\r\n");
        if ($written === false) {
            throw new Exception("No se pudo enviar comando SMTP: {$command}");
        }
    }

    /**
     * Lee y valida la respuesta SMTP.
     * - Si $multiLine = true, consume todas las líneas con el mismo código y guion (e.g., '250-').
     */
    private function smtpExpect($fp, int $expectedCode, bool $multiLine = false): void
    {
        $response = '';
        $code = null;

        do {
            $line = @fgets($fp, 2048);
            if ($line === false) {
                throw new Exception('No se recibió respuesta del servidor SMTP.');
            }
            $response .= $line;

            if (strlen($line) >= 3 && ctype_digit(substr($line, 0, 3))) {
                $code = (int) substr($line, 0, 3);
                $isMore = (isset($line[3]) && $line[3] === '-');

                if (!$multiLine || !$isMore) {
                    break; // fin si no es multi-línea o ya llegó la última línea (espacio)
                }
            } else {
                break;
            }
        } while (true);

        if ($code !== $expectedCode) {
            throw new Exception("Respuesta SMTP inesperada. Esperado {$expectedCode}, recibido {$code}. Respuesta: " . trim($response));
        }
    }

    /**
     * Devuelve un nombre razonable para EHLO (dominio del usuario o 'orbitflow.local').
     */
    private function guessEhloName(?string $user): string
    {
        if ($user && str_contains($user, '@')) {
            $domain = substr(strrchr($user, '@'), 1) ?: null;
            if ($domain) return $domain;
        }
        return 'orbitflow.local';
    }
}
