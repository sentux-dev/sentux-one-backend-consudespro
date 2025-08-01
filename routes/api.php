<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Crm\ActivityController;
use App\Http\Controllers\Api\Crm\ContactAdvancedInfoController;
use App\Http\Controllers\Api\Crm\ContactAssociationController;
use App\Http\Controllers\Api\User\Profile\MfaController;
use App\Http\Controllers\Api\User\Profile\PersonalDataController;
use App\Http\Controllers\Api\User\Profile\PreferencesController;
use App\Http\Controllers\Api\User\Profile\SecurityDataController;
use App\Http\Controllers\Api\User\SessionController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\Crm\ContactController;
use App\Http\Controllers\Api\Crm\ContactCustomFieldController;
use App\Http\Controllers\Api\Crm\ContactCustomFieldValueController;
use App\Http\Controllers\Api\Crm\ContactLookupController;
use App\Http\Controllers\Api\Crm\DealController;
use App\Http\Controllers\Api\Crm\DealCustomFieldController;
use App\Http\Controllers\Api\Crm\DealCustomFieldValueController;
use App\Http\Controllers\Api\Crm\LeadActionController;
use App\Http\Controllers\Api\Crm\LeadController;
use App\Http\Controllers\Api\Crm\LeadImportController;
use App\Http\Controllers\Api\Crm\LeadProcessingLogController;
use App\Http\Controllers\Api\Crm\LeadSourceController;
use App\Http\Controllers\Api\Crm\LeadWebhookController;
use App\Http\Controllers\Api\Crm\PipelineController;
use App\Http\Controllers\Api\Crm\TaskController;
use App\Http\Controllers\Api\Crm\WorkflowController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Marketing\CampaignController;
use App\Http\Controllers\Api\Marketing\MailingListController;
use App\Http\Controllers\Api\Marketing\SegmentController;
use App\Http\Controllers\Api\Marketing\WebhookController;
use App\Http\Controllers\Api\RealState\DocumentController;
use App\Http\Controllers\Api\RealState\ExtraController;
use App\Http\Controllers\Api\RealState\HouseModelController;
use App\Http\Controllers\Api\RealState\LotAdjustmentController;
use App\Http\Controllers\Api\RealState\LotController;
use App\Http\Controllers\Api\RealState\ProjectController;
use App\Http\Controllers\Api\User\UserGroupController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/verify-email-login', [AuthController::class, 'verifyEmailLoginCode']);
Route::post('/auth/verify-app-login', [AuthController::class, 'verifyAppLoginCode']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User routes
    Route::prefix('user')->group(function () {
        Route::prefix('profile')->group(function () {
            // Información personal
            Route::get('personal-data', [PersonalDataController::class, 'show']);
            Route::put('personal-data', [PersonalDataController::class, 'update']);

            // Preferencias
            Route::get('preferences', [PreferencesController::class, 'show']);
            Route::put('preferences', [PreferencesController::class, 'update']);

            // Seguridad
            Route::get('security-data', [SecurityDataController::class, 'show']);
            Route::put('security-data', [SecurityDataController::class, 'update']);
            Route::put('security-data/password', [SecurityDataController::class, 'updatePassword']);

            //  MFA
            Route::prefix('mfa')->group(function () {
                Route::post('email/send', [MfaController::class, 'sendEmailCode']);
                Route::post('email/verify', [MfaController::class, 'verifyEmailCode']);
                Route::post('app/setup', [MfaController::class, 'setupTOTP']);
                Route::post('app/verify', [MfaController::class, 'verifyTOTP']);
                Route::delete('deactivate', [MfaController::class, 'deactivateMFA']);
            });
        });
        // Sesiones activas
        Route::get('sessions', [SessionController::class, 'index']);
        Route::delete('sessions/{id}', [SessionController::class, 'destroy']);
    });

    // Lista de usuarios
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    Route::apiResource('user-groups', UserGroupController::class);

    // Rutas de CRM
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);
    Route::put('/contacts/{contact}', [ContactController::class, 'update']);
    Route::put('/contacts/{contact}/basic', [ContactController::class, 'updateBasic']);
    Route::patch('/contacts/{contact}/patch', [ContactController::class, 'updatePatch']);
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
    Route::patch('/contacts/{contact}/reactivate', [ContactController::class, 'reactivate']);
    Route::get('/contacts/{contact}/advanced-info', [ContactAdvancedInfoController::class, 'show']);
    Route::patch('/contacts/{contact}/advanced-info', [ContactAdvancedInfoController::class, 'update']);
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::prefix('crm')->group(function () {
        // Contactos
        Route::get('contacts/{id}/activities', [ActivityController::class, 'index']);
        Route::get('contacts/{contactId}/custom-fields', [ContactCustomFieldValueController::class, 'index']);
        Route::post('contacts/{contactId}/custom-fields', [ContactCustomFieldValueController::class, 'storeOrUpdate']);
        // Actividades
        Route::post('activities', [ActivityController::class, 'store']);
        Route::delete('activities/{activity}', [ActivityController::class, 'destroy']); // Opcional
        // Vista tipo tabla general
        Route::get('/tasks', [TaskController::class, 'listTasks']);
        // Tareas de un contacto específico (Tab en Contact Detail)
        Route::get('/contacts/{contact}/tasks', [TaskController::class, 'listTasksByContact']);
        // Tareas CRUD
        Route::post('/tasks', [TaskController::class, 'createTask']);
        Route::put('/tasks/{task}', [TaskController::class, 'updateTask']);
        Route::delete('/tasks/{task}', [TaskController::class, 'deleteTask']);
        // Custom Fields
        Route::get('/custom-fields', [ContactCustomFieldController::class, 'index']);
        Route::post('/custom-fields', [ContactCustomFieldController::class, 'store']);
        Route::put('/custom-fields/{field}', [ContactCustomFieldController::class, 'update']);
        Route::post('/custom-fields/{field}/desactivate', [ContactCustomFieldController::class, 'desactivate']);

        Route::prefix('contacts/{contact}/associations')->group(function () {
            Route::get('/', [ContactAssociationController::class, 'index']);
            Route::post('/', [ContactAssociationController::class, 'store']);
            Route::delete('{id}', [ContactAssociationController::class, 'destroy']);
            // Nota: Para la eliminación de asociaciones polimórficas (como Deal a Contact),
            // la ruta DELETE puede necesitar ajustarse si el `id` no es el `association_id` de la tabla pivot.
            // Considera enviar `deal_id` y `contact_id` en el cuerpo de la solicitud DELETE
            // o crear una ruta específica para la eliminación de DealAssociation.
        });

        Route::get('/deals', [DealController::class, 'index']);
        Route::post('/deals', [DealController::class, 'store']); // Ya maneja la asociación con contacto
        Route::put('/deals/{deal}', [DealController::class, 'update']);
        Route::get('/deals/custom-fields', [DealCustomFieldController::class, 'index']);
        Route::get('/deals/{deal}', [DealController::class, 'show']);
        Route::get('/deals/{deal}/custom-fields', [DealCustomFieldValueController::class, 'index']);
        Route::post('/deals/{deal}/custom-fields', [DealCustomFieldValueController::class, 'storeOrUpdate']);

        Route::prefix('settings/pipelines')->group(function () {
            Route::get('/', [PipelineController::class, 'index']);
            Route::post('/', [PipelineController::class, 'store']);
            Route::put('{pipeline}', [PipelineController::class, 'update']);
            Route::delete('{pipeline}', [PipelineController::class, 'destroy']);

            Route::post('{pipeline}/stages', [PipelineController::class, 'addStage']);
            Route::put('stages/{stage}', [PipelineController::class, 'updateStage']);
            Route::delete('stages/{stage}', [PipelineController::class, 'deleteStage']);

            Route::post('{pipeline}/reorder-stages', [PipelineController::class, 'reorderStages']);
        });

        Route::prefix('lookups')->group(function () {
            Route::get('/projects', [ContactLookupController::class, 'projects']);
            Route::get('/campaigns', [ContactLookupController::class, 'campaigns']);
            Route::get('/origins', [ContactLookupController::class, 'origins']);
            Route::get('/owners', [ContactLookupController::class, 'owners']);
            Route::get('/status', [ContactLookupController::class, 'status']);
            Route::get('/disqualification_reasons', [ContactLookupController::class, 'disqualificationReasons']);
        });

        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('/leads/{externalLead}/execute-action', [LeadActionController::class, 'executeAction'])->name('leads.executeAction');
        Route::post('/leads/import', [LeadImportController::class, 'store'])->name('leads.import');
        Route::post('/leads/import/analyze', [LeadImportController::class, 'analyze'])->name('leads.import.analyze');
        Route::post('/leads/import/process', [LeadImportController::class, 'process'])->name('leads.import.process');
        Route::get('/lead-logs', [LeadProcessingLogController::class, 'index'])->name('leads.logs.index');


        Route::apiResource('workflows', WorkflowController::class);
        Route::apiResource('lead-sources', LeadSourceController::class);
    });
    // Proyectos Inmobiliarios
    Route::prefix('real-estate')->group(function () {
        
        // Proyectos
        Route::apiResource('projects', ProjectController::class);

        // Modelos de Casa (anidados bajo proyecto)
        Route::apiResource('projects.house-models', HouseModelController::class)->only(['index', 'store']);
        Route::apiResource('house-models', HouseModelController::class)->except(['index', 'store']);
        
        // Extras (anidados bajo proyecto)
        Route::apiResource('projects.extras', ExtraController::class)->only(['index', 'store']);
        Route::apiResource('extras', ExtraController::class)->except(['index', 'store']);

        // Lotes (rutas individuales)
        Route::get('lots/{lot:slug}', [LotController::class, 'show'])->name('lots.show');
        Route::put('lots/{lot:slug}', [LotController::class, 'update'])->name('lots.update');

        // Ajustes de Lote (anidados bajo lote)
        // POST /api/real-estate/lots/{lot:slug}/adjustments
        Route::post('lots/{lot:slug}/adjustments', [LotAdjustmentController::class, 'store'])->name('lots.adjustments.store');
        // DELETE /api/real-estate/lot-adjustments/{adjustment}
        Route::delete('lot-adjustments/{adjustment}', [LotAdjustmentController::class, 'destroy'])->name('lots.adjustments.destroy');

        // Documentos de Lote (anidados bajo lote)
        // GET /api/real-estate/lots/{lot:slug}/documents
        // POST /api/real-estate/lots/{lot:slug}/documents
        Route::post('lots/{lot:slug}/documents', [DocumentController::class, 'store'])->name('lots.documents.store');
        Route::get('lots/{lot:slug}/documents', [DocumentController::class, 'index'])->name('lots.documents.index');
        // DELETE /api/real-estate/documents/{document}
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('lots.documents.destroy');

    });

    Route::prefix('marketing')->name('marketing.')->group(function () {
        
        // Rutas para gestionar campañas
        Route::apiResource('campaigns', CampaignController::class);
        // Ruta específica para enviar una campaña
        Route::post('campaigns/{campaign:slug}/send', [CampaignController::class, 'send'])->name('campaigns.send');
        // Ruta para enviar una prueba
        Route::post('campaigns/{campaign:slug}/send-test', [CampaignController::class, 'sendTest'])->name('campaigns.send-test');

        // Rutas para gestionar listas de correo
        Route::apiResource('mailing-lists', MailingListController::class);
        
        // Rutas para gestionar segmentos
        Route::apiResource('segments', SegmentController::class);
        // Ruta para previsualizar los contactos de un segmento
        Route::post('segments/preview', [SegmentController::class, 'preview'])->name('segments.preview');
        // Ruta para exportar contactos de un segmento a CSV
        Route::get('campaigns/{campaign:slug}/export-csv', [CampaignController::class, 'exportCsv'])->name('campaigns.export');

    });
});

// Ruta pública para el Webhook (fuera del middleware de autenticación)
Route::post('webhooks/email-events', [WebhookController::class, 'handleMandrill'])->name('webhooks.mandrill');

Route::post('/leads/ingress/{source}', [LeadWebhookController::class, 'ingress'])
    ->name('leads.ingress')
    ->middleware('webhook.validate');