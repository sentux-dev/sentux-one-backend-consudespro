<?php
namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return Permission::orderBy('label')->get();
    }
}