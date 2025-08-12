<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CRM\ActivityController;
use App\Http\Controllers\Api\CRM\CampaignController as CRMCampaignController;
use App\Http\Controllers\Api\CRM\CompanyController;
use App\Http\Controllers\Api\CRM\CompanyLookupController;
use App\Http\Controllers\Api\CRM\ContactAdvancedInfoController;
use App\Http\Controllers\Api\CRM\ContactAssociationController;
use App\Http\Controllers\Api\User\Profile\MfaController;
use App\Http\Controllers\Api\User\Profile\PersonalDataController;
use App\Http\Controllers\Api\User\Profile\PreferencesController;
use App\Http\Controllers\Api\User\Profile\SecurityDataController;
use App\Http\Controllers\Api\User\SessionController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\CRM\ContactController;
use App\Http\Controllers\Api\CRM\ContactCustomFieldController;
use App\Http\Controllers\Api\CRM\ContactCustomFieldValueController;
use App\Http\Controllers\Api\CRM\ContactLookupController;
use App\Http\Controllers\Api\CRM\ContactStatusController;
use App\Http\Controllers\Api\CRM\DealController;
use App\Http\Controllers\Api\CRM\DealCustomFieldController;
use App\Http\Controllers\Api\CRM\DealCustomFieldValueController;
use App\Http\Controllers\Api\CRM\DealLookupController;
use App\Http\Controllers\Api\CRM\DisqualificationReasonController;
use App\Http\Controllers\Api\CRM\EmailTemplateController;
use App\Http\Controllers\Api\CRM\FacebookIntegrationController;
use App\Http\Controllers\Api\CRM\FacebookWebhookController;
use App\Http\Controllers\Api\CRM\LeadActionController;
use App\Http\Controllers\Api\CRM\LeadController;
use App\Http\Controllers\Api\CRM\LeadImportController;
use App\Http\Controllers\Api\CRM\LeadImportHistoryController;
use App\Http\Controllers\Api\CRM\LeadProcessingLogController;
use App\Http\Controllers\Api\CRM\LeadSourceController;
use App\Http\Controllers\Api\CRM\LeadWebhookController;
use App\Http\Controllers\Api\CRM\OriginController;
use App\Http\Controllers\Api\CRM\PipelineController;
use App\Http\Controllers\Api\CRM\SequenceController;
use App\Http\Controllers\Api\CRM\TaskController;
use App\Http\Controllers\Api\CRM\WorkflowController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\Marketing\CampaignController;
use App\Http\Controllers\Api\Marketing\MailingListController;
use App\Http\Controllers\Api\Marketing\SegmentController;
use App\Http\Controllers\Api\Marketing\SubscriptionController;
use App\Http\Controllers\Api\Marketing\WebhookController;
use App\Http\Controllers\Api\RealState\DocumentController;
use App\Http\Controllers\Api\RealState\ExtraController;
use App\Http\Controllers\Api\RealState\HouseModelController;
use App\Http\Controllers\Api\RealState\LotAdjustmentController;
use App\Http\Controllers\Api\RealState\LotController;
use App\Http\Controllers\Api\RealState\ProjectController;
use App\Http\Controllers\Api\User\UserGroupController;
use App\Http\Controllers\Api\Settings\IntegrationController;
use App\Http\Controllers\Api\Settings\PermissionController;
use App\Http\Controllers\Api\Settings\PermissionRuleController;
use App\Http\Controllers\Api\Settings\RoleController;
use App\Http\Controllers\Api\User\Profile\ThemePreferencesController;
use App\Http\Controllers\Api\NotificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/verify-email-login', [AuthController::class, 'verifyEmailLoginCode']);
Route::post('/auth/verify-app-login', [AuthController::class, 'verifyAppLoginCode']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');

Route::get('/facebook/webhook', [FacebookWebhookController::class, 'verify']);
Route::post('/facebook/webhook', [FacebookWebhookController::class, 'handle']);
Route::get('/facebook/callback', [FacebookIntegrationController::class, 'handleCallback']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{notification}/mark-as-read', [NotificationController::class, 'markOneAsRead']);

    Route::get('/facebook/auth-url', [FacebookIntegrationController::class, 'getAuthUrl']);
    Route::get('/facebook/pages', [FacebookIntegrationController::class, 'getPages']);
    Route::get('/facebook/pages/{pageId}/forms', [FacebookIntegrationController::class, 'getFormsForPage']);
    Route::post('/facebook/subscribe-page', [FacebookIntegrationController::class, 'subscribePage']);


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

            Route::get('theme-preferences', [ThemePreferencesController::class, 'show']);
            Route::put('theme-preferences', [ThemePreferencesController::class, 'update']);
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
    Route::get('/contacts/{contact}/association-history/{type}', [ContactController::class, 'getAssociationHistory']);
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
        // Compañías
        Route::apiResource('companies', CompanyController::class);
        // Contactos
        Route::post('contacts/{contact}/add-association-history', [ContactController::class, 'addAssociationHistory']);
        Route::get('contacts/{id}/activities', [ActivityController::class, 'index']);
        Route::get('contacts/{contactId}/custom-fields', [ContactCustomFieldValueController::class, 'index']);
        Route::post('contacts/{contactId}/custom-fields', [ContactCustomFieldValueController::class, 'storeOrUpdate']);
        Route::apiResource('contact-statuses', ContactStatusController::class);
        // Status de contacto
        Route::post('contact-statuses/update-order', [ContactStatusController::class, 'updateOrder']);
        // Empresas
        Route::get('lookups/companies-search', [CompanyLookupController::class, 'search']);

        Route::apiResource('companies', CompanyController::class);

        Route::apiResource('sequences', SequenceController::class);
        Route::post('contacts/{contact}/enroll-in-sequence', [ContactController::class, 'enrollInSequence']);

        Route::get('contacts/{contact}/sequence-enrollments', [ContactController::class, 'getSequenceEnrollments']);
        Route::post('contacts/{contact}/sequence-enrollments/{enrollment}/stop', [ContactController::class, 'stopSequenceEnrollment']);

        Route::apiResource('email-templates', EmailTemplateController::class);

        // Razones de descalificación
        Route::post('disqualification-reasons/update-order', [DisqualificationReasonController::class, 'updateOrder']);
        Route::apiResource('disqualification-reasons', DisqualificationReasonController::class);
        // Campañas publicitarias
        Route::post('campaigns/update-order', [CRMCampaignController::class, 'updateOrder']);
        Route::apiResource('campaigns', CRMCampaignController::class);
        // Orígenes de contacto
        Route::post('origins/update-order', [OriginController::class, 'updateOrder']);
        Route::apiResource('origins', OriginController::class);
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
        Route::apiResource('custom-fields', ContactCustomFieldController::class);


        Route::prefix('contacts/{contact}/associations')->group(function () {
            Route::get('/', [ContactAssociationController::class, 'index']);
            Route::post('/', [ContactAssociationController::class, 'store']);
            Route::put('/{type}/{associationId}', [ContactAssociationController::class, 'update']);
            Route::delete('/{type}/{associationId}', [ContactAssociationController::class, 'destroy']);
        });
        Route::get('/deals/lookups/custom-fields', [DealLookupController::class, 'customFields']);
        Route::get('/deals/{deal}/association-status/{contactId}', [DealController::class, 'getAssociationStatus']);
        Route::get('/deals', [DealController::class, 'index']);
        Route::post('/deals', [DealController::class, 'store']); // Ya maneja la asociación con contacto
        Route::put('/deals/{deal}', [DealController::class, 'update']);
        Route::apiResource('deals/custom-fields', DealCustomFieldController::class);
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
            Route::get('/contact-fields', [ContactLookupController::class, 'contactFields']);
        });

        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('/leads/{externalLead}/execute-action', [LeadActionController::class, 'executeAction'])->name('leads.executeAction');
        Route::post('/leads/bulk-action', [LeadActionController::class, 'bulkExecuteAction'])->name('leads.bulkAction');
        Route::post('/leads/process-all-pending', [LeadActionController::class, 'processAllPending'])->name('leads.processAll');
        Route::post('/leads/import', [LeadImportController::class, 'store'])->name('leads.import');
        Route::post('/leads/import/analyze', [LeadImportController::class, 'analyze'])->name('leads.import.analyze');
        Route::post('/leads/import/process', [LeadImportController::class, 'process'])->name('leads.import.process');
        Route::apiResource('lead-imports', LeadImportHistoryController::class)->only(['index', 'destroy']);
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
        Route::post('campaigns/validate-template', [CampaignController::class, 'validateTemplate'])->name('campaigns.validate-template');

    });

    Route::prefix('settings')->group(function() {
    // ... (otras rutas de settings)
        Route::apiResource('integrations', IntegrationController::class)->except(['store', 'destroy']);
        Route::patch('/integrations/{integration}/name', [IntegrationController::class, 'updateName']);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::apiResource('roles.permissions.rules', PermissionRuleController::class)->only(['index', 'store'])->shallow();
        Route::delete('permission-rules/{permissionRule}', [PermissionRuleController::class, 'destroy'])->name('permission-rules.destroy');
    });
});

// Ruta pública para el Webhook (fuera del middleware de autenticación)
Route::post('webhooks/email-events', [WebhookController::class, 'handleMandrill'])->name('webhooks.mandrill');

Route::post('/leads/ingress/{source}', [LeadWebhookController::class, 'ingress'])
    ->name('leads.ingress')
    ->middleware('webhook.validate');

Route::prefix('marketing')->group(function () {
    // Usamos el UUID para la seguridad, Laravel lo encontrará automáticamente.
    Route::get('/unsubscribe/{contact:uuid}', [SubscriptionController::class, 'getContactForUnsubscribe'])->name('unsubscribe.show');
    Route::post('/unsubscribe/{contact:uuid}', [SubscriptionController::class, 'processUnsubscribe'])->name('unsubscribe.process');
    Route::get('/update-profile/{contact:uuid}', [SubscriptionController::class, 'getContactForUpdateProfile'])->name('profile.show');
    Route::post('/update-profile/{contact:uuid}', [SubscriptionController::class, 'processProfileUpdate'])->name('profile.process');
});