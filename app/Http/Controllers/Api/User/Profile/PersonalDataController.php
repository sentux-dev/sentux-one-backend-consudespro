<?php

namespace App\Http\Controllers\Api\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PersonalDataController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'firstName' => $user->first_name,
            'lastName'  => $user->last_name,
            'phone'     => $user->phone,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'firstName' => ['required', 'string', 'max:100'],
            'lastName'  => ['required', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:25'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->first_name = $request->input('firstName');
        $user->last_name  = $request->input('lastName');
        $user->phone      = $request->input('phone');

        // El evento saving actualizará el campo name automáticamente
        $user->save();

        return response()->json([
            'message' => 'Información personal actualizada correctamente'
        ]);
    }
}
