<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Controller;
use App\Models\Crm\DealCustomField;

class DealCustomFieldController extends Controller
{
    public function index()
    {
        return DealCustomField::orderBy('id')->get();
    }
}