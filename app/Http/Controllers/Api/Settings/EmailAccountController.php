<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserEmailAccount;
use App\Services\EmailAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $validatedData = $this->validateAccountData($request);
        $result = $emailAccountService->testConnection($validatedData);
        
        return response()->json($result, $result['success'] ? 200 : 422);
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
        return $request->validate([
            'email_address'   => 'required|email',
            'connection_type' => ['required', Rule::in(['generic_imap'])],
            
            'smtp_host'       => 'required|string',
            'smtp_port'       => 'required|integer',
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_username'   => 'required|string',
            
            'imap_host'       => 'required|string',
            'imap_port'       => 'required|integer',
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'imap_username'   => 'required|string',
            
            'password'        => 'required|string',
        ]);
    }
}