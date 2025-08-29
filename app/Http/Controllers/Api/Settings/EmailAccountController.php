<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserEmailAccount;
use App\Services\EmailAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EmailAccountController extends Controller
{
    /**
     * Devuelve una lista de todas las cuentas de correo conectadas por el usuario autenticado.
     */
    public function index(Request $request)
    {
        return $request->user()->emailAccounts()->get();
    }

    /**
     * Valida y guarda una nueva cuenta de correo para el usuario autenticado.
     */
    public function store(Request $request, EmailAccountService $emailAccountService)
    {
        $validatedData = $this->validateAccountData($request);

        // 1. Primero, probamos que las credenciales son válidas antes de guardar.
        $testResult = $emailAccountService->testConnection($validatedData);

        if (!$testResult['success']) {
            // Si la conexión falla, devolvemos un error de validación claro.
            return response()->json([
                'message' => 'No se pudo conectar con las credenciales proporcionadas.',
                'error' => $testResult['message']
            ], 422);
        }

        // 2. Si la conexión es exitosa, guardamos la cuenta.
        $account = $emailAccountService->createOrUpdateAccount($request->user(), $validatedData);

        return response()->json($account, 201);
    }

    /**
     * Endpoint dedicado para probar las credenciales sin guardarlas.
     */
    public function testConnection(Request $request, EmailAccountService $emailAccountService)
    {
        try {
            $payload = $this->validateAccountData($request);

            // Invoca al servicio con un payload plano
            $result = $emailAccountService->testConnection($payload);

            return response()->json($result, !empty($result['success']) ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La validación de la cuenta falló.',
                'errors'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo probar la conexión.',
                'error'   => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Error interno',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Elimina una cuenta de correo conectada.
     */
    public function destroy(Request $request, UserEmailAccount $account)
    {
        // Política de seguridad simple: Asegurarse de que el usuario solo pueda borrar sus propias cuentas.
        if ($request->user()->id !== $account->user_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $account->delete();

        return response()->noContent();
    }

    /**
     * Método de ayuda para centralizar las reglas de validación.
     */
    private function validateAccountData(Request $request): array
    {
        // --- Modo: usar cuenta guardada por account_id ---
        if ($request->filled('account_id')) {
            $data = $request->validate([
                'account_id' => ['required', 'integer'],
                // opcional: permitir sobreescribir password en la prueba
                'password'   => ['nullable', 'string'],
            ]);

            /** @var \App\Models\Settings\UserEmailAccount $account */
            $account = UserEmailAccount::query()
                ->where('id', $data['account_id'])
                ->where('user_id', Auth::id())
                ->first();

            if (!$account) {
                throw ValidationException::withMessages([
                    'account_id' => ['No se encontró la cuenta o no pertenece al usuario.'],
                ]);
            }

            // ✅ Si viene password en la request, úsalo; si no, toma el del modelo.
            //    IMPORTANTE: si el modelo tiene cast 'encrypted', $account->password YA está desencriptado.
            $password = $request->input('password') ?: $account->password;

            if (blank($password)) {
                throw ValidationException::withMessages([
                    'password' => ['No hay contraseña disponible para realizar la prueba.'],
                ]);
            }

            return $this->normalizeForService([
                'email_address'   => $account->email_address,
                'password'        => $password,

                'imap_host'       => $account->imap_host,
                'imap_port'       => $account->imap_port,
                'imap_encryption' => $account->imap_encryption,
                'imap_username'   => $account->imap_username ?: $account->email_address,

                'smtp_host'       => $account->smtp_host,
                'smtp_port'       => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username'   => $account->smtp_username ?: $account->email_address,

                'is_active'       => (bool) $account->is_active,
            ]);
        }

        // --- Modo: credenciales completas desde el body ---
        $validated = $request->validate([
            'email_address'   => ['required', 'email'],
            'password'        => ['required', 'string'],

            'imap_host'       => ['required', 'string'],
            'imap_port'       => ['nullable', 'integer'],
            'imap_encryption' => ['nullable', Rule::in(['none','ssl','tls','starttls'])],
            'imap_username'   => ['nullable', 'string'],

            'smtp_host'       => ['required', 'string'],
            'smtp_port'       => ['nullable', 'integer'],
            'smtp_encryption' => ['nullable', Rule::in(['none','ssl','tls','starttls'])],
            'smtp_username'   => ['nullable', 'string'],

            'is_active'       => ['sometimes', 'boolean'],
        ]);

        return $this->normalizeForService($validated);
    }

    /**
     * Normaliza encriptación/puertos/usuario y completa defaults.
     */
    private function normalizeForService(array $data): array
    {
        $imapEnc  = strtolower($data['imap_encryption']  ?? 'ssl');      // default SSL
        $smtpEnc  = strtolower($data['smtp_encryption']  ?? 'ssl');

        // Defaults de puertos si vienen vacíos
        $data['imap_port'] = (int)($data['imap_port'] ?? ($imapEnc === 'ssl' ? 993 : 143));
        $data['smtp_port'] = (int)($data['smtp_port'] ?? ($smtpEnc === 'ssl' ? 465 : 587));

        // Normaliza valores válidos
        $imapEnc  = in_array($imapEnc, ['none', 'ssl', 'tls', 'starttls'], true) ? $imapEnc : 'ssl';
        $smtpEnc  = in_array($smtpEnc, ['none', 'ssl', 'tls', 'starttls'], true) ? $smtpEnc : 'ssl';

        $data['imap_encryption'] = $imapEnc;
        $data['smtp_encryption'] = $smtpEnc;

        // Usernames por defecto al email_address
        $data['imap_username'] = $data['imap_username'] ?: $data['email_address'];
        $data['smtp_username'] = $data['smtp_username'] ?: $data['email_address'];

        // Boolean
        $data['is_active'] = (bool)($data['is_active'] ?? true);

        // Sanitiza strings
        foreach (['email_address','password','imap_host','smtp_host','imap_username','smtp_username'] as $k) {
            if (isset($data[$k]) && is_string($data[$k])) {
                $data[$k] = trim($data[$k]);
            }
        }

        return [
            'email_address'   => $data['email_address'],
            'password'        => $data['password'],

            'imap_host'       => $data['imap_host'],
            'imap_port'       => (int)$data['imap_port'],
            'imap_encryption' => $data['imap_encryption'],
            'imap_username'   => $data['imap_username'],

            'smtp_host'       => $data['smtp_host'],
            'smtp_port'       => (int)$data['smtp_port'],
            'smtp_encryption' => $data['smtp_encryption'],
            'smtp_username'   => $data['smtp_username'],

            'is_active'       => (bool)$data['is_active'],
            'user_id'         => Auth::id(), // por si tu servicio lo usa para logging
        ];
    }
}