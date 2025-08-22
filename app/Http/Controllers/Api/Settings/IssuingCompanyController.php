<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Settings\IssuingCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IssuingCompanyController extends Controller
{
    /**
     * Devuelve una lista de todas las empresas emisoras.
     */
    public function index()
    {
        return IssuingCompany::orderBy('name')->get();
    }

    /**
     * Guarda una nueva empresa emisora en la base de datos.
     */
     public function store(Request $request)
    {
        $validated = $this->validateCompany($request);
        $dataToCreate = $this->prepareData($request);
        $company = IssuingCompany::create($dataToCreate);

        return response()->json($company, 201);
    }

    public function update(Request $request, IssuingCompany $issuingCompany)
    {
        $validated = $this->validateCompany($request, $issuingCompany->id);
        $dataToUpdate = $this->prepareData($request, $issuingCompany);
        $issuingCompany->update($dataToUpdate);

        return response()->json($issuingCompany->fresh());
    }

    public function destroy(IssuingCompany $issuingCompany)
    {
        if ($issuingCompany->logo_path) {
            Storage::disk('public')->delete($issuingCompany->logo_path);
        }
        $issuingCompany->delete();
        return response()->noContent();
    }

    /**
     * Valida los datos de la empresa para store y update.
     */
    private function validateCompany(Request $request, ?int $ignoreId = null): array
    {
        $taxIdRule = 'required|string|max:50';
        $nameRule = 'required|string|max:255';

        if ($ignoreId) {
            $taxIdRule = Rule::unique('settings_issuing_companies')->ignore($ignoreId);
            $nameRule = Rule::unique('settings_issuing_companies')->ignore($ignoreId);
        } else {
            $taxIdRule .= '|unique:settings_issuing_companies';
            $nameRule .= '|unique:settings_issuing_companies';
        }

        return $request->validate([
            'name' => $nameRule,
            'legal_name' => 'required|string|max:255',
            'tax_id' => $taxIdRule,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png|max:1024',
            'bank_accounts' => 'nullable|json',
            'pdf_header_text' => 'nullable|string|max:1000',
            'pdf_footer_text' => 'nullable|string|max:1000',
            'default_notes' => 'nullable|string|max:2000',
        ]);
    }

    /**
     * Prepara los datos para la creación o actualización.
     */
    private function prepareData(Request $request, ?IssuingCompany $company = null): array
    {
        $data = $request->except(['logo', 'bank_accounts']);

        if ($request->hasFile('logo')) {
            if ($company && $company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company_logos', 'public');
        }

        if ($request->has('bank_accounts')) {
            $data['bank_accounts'] = json_decode($request->input('bank_accounts'), true);
        }

        return $data;
    }
}