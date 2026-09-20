<?php

declare(strict_types=1);

use App\Http\Controllers\AssessorRouteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CommunicationConfigController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactExportController;
use App\Http\Controllers\ContactImportController;
use App\Http\Controllers\ContactInteractionController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFolderController;
use App\Http\Controllers\DonorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\IntegrationContactController;
use App\Http\Controllers\IntegrationSourceController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\PropositionController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TerritoryController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Auth (público) ──────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// ── Formulários públicos (sem auth) ─────────────────────────────
Route::prefix('v1/public/forms')->group(function () {
    Route::get('{slug}', [PublicFormController::class, 'show']);
    Route::post('{slug}/submit', [PublicFormController::class, 'submit']);
});

// ── Webhooks (sem auth, validação interna) ──────────────────────
Route::prefix('webhooks')->group(function () {
    Route::post('ses', [WebhookController::class, 'ses']);
    Route::post('whatsapp', [WebhookController::class, 'whatsapp']);
});

// ── Integrações externas (sem auth Sanctum, autenticado via token) ─
Route::middleware('throttle:60,1')->post('v1/integrations/contacts', [IntegrationContactController::class, 'store']);

// ── Descadastro (link assinado, sem auth) ───────────────────────
Route::middleware('signed')->get('v1/public/unsubscribe/{recipient}', [UnsubscribeController::class, 'handle'])
    ->name('unsubscribe.handle');

// ── Rotas autenticadas ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'tenant.active'])->prefix('v1')->group(function () {

    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'updatePassword']);

    // Módulo 10: Equipe e Acessos
    Route::prefix('team')->group(function () {
        Route::get('users', [TeamController::class, 'index']);
        Route::post('users', [TeamController::class, 'store']);
        Route::get('users/{id}', [TeamController::class, 'show']);
        Route::put('users/{id}', [TeamController::class, 'update']);
        Route::delete('users/{id}', [TeamController::class, 'destroy']);
        Route::post('users/{id}/activate', [TeamController::class, 'activate']);
        Route::post('users/{id}/deactivate', [TeamController::class, 'deactivate']);
        Route::get('audit-logs', [TeamController::class, 'auditLogs']);
        Route::get('settings', [TeamController::class, 'settings']);
        Route::put('settings', [TeamController::class, 'updateSettings']);
        Route::post('settings/logo', [TeamController::class, 'uploadLogo']);
        Route::delete('settings/logo', [TeamController::class, 'deleteLogo']);
    });

    // Módulo 01: Contatos
    Route::prefix('contacts')->group(function () {
        Route::get('export', [ContactExportController::class, 'index']);
        Route::post('import', [ContactImportController::class, 'store']);
        Route::post('import/{id}/map', [ContactImportController::class, 'map']);
        Route::get('import/{id}', [ContactImportController::class, 'show']);
        Route::get('/', [ContactController::class, 'index']);
        Route::post('/', [ContactController::class, 'store']);
        Route::get('{ulid}', [ContactController::class, 'show']);
        Route::put('{ulid}', [ContactController::class, 'update']);
        Route::delete('{ulid}', [ContactController::class, 'destroy']);
        Route::post('{ulid}/restore', [ContactController::class, 'restore']);
        Route::get('{ulid}/interactions', [ContactInteractionController::class, 'index']);
        Route::post('{ulid}/interactions', [ContactInteractionController::class, 'store']);
        Route::post('{ulid}/tags', [TagController::class, 'attach']);
        Route::delete('{ulid}/tags/{tagId}', [TagController::class, 'detach']);
        Route::post('{ulid}/photo', [ContactController::class, 'uploadPhoto']);
    });

    // Tags
    Route::apiResource('tags', TagController::class)->except(['show']);

    // Segmentos
    Route::prefix('segments')->group(function () {
        Route::get('/', [SegmentController::class, 'index']);
        Route::post('/', [SegmentController::class, 'store']);
        Route::get('{ulid}', [SegmentController::class, 'show']);
        Route::put('{ulid}', [SegmentController::class, 'update']);
        Route::delete('{ulid}', [SegmentController::class, 'destroy']);
        Route::post('{ulid}/recalculate', [SegmentController::class, 'recalculate']);
        Route::get('{ulid}/contacts', [SegmentController::class, 'contacts']);
        Route::post('{ulid}/contacts', [SegmentController::class, 'addContact']);
        Route::delete('{ulid}/contacts/{contactUlid}', [SegmentController::class, 'removeContact']);
    });

    // Campos personalizados
    Route::apiResource('custom-fields', CustomFieldController::class)->except(['show']);

    // Fontes de integração (apps externos que criam contatos)
    Route::prefix('integration-sources')->group(function () {
        Route::get('/', [IntegrationSourceController::class, 'index']);
        Route::post('/', [IntegrationSourceController::class, 'store']);
        Route::put('{ulid}', [IntegrationSourceController::class, 'update']);
        Route::delete('{ulid}', [IntegrationSourceController::class, 'destroy']);
        Route::post('{ulid}/regenerate-token', [IntegrationSourceController::class, 'regenerateToken']);
    });

    // Módulo 02: Geolocalização
    Route::prefix('geo')->group(function () {
        Route::get('contacts', [GeoController::class, 'contacts']);
        Route::get('heatmap', [GeoController::class, 'heatmap']);
        Route::get('territories', [TerritoryController::class, 'index']);
        Route::post('territories', [TerritoryController::class, 'store']);
        Route::put('territories/{id}', [TerritoryController::class, 'update']);
        Route::delete('territories/{id}', [TerritoryController::class, 'destroy']);
        Route::get('contacts-in-territory/{id}', [TerritoryController::class, 'contactsInTerritory']);
        Route::get('routes', [AssessorRouteController::class, 'index']);
        Route::post('routes', [AssessorRouteController::class, 'store']);
        Route::get('routes/{ulid}', [AssessorRouteController::class, 'show']);
        Route::put('routes/{ulid}', [AssessorRouteController::class, 'update']);
        Route::delete('routes/{ulid}', [AssessorRouteController::class, 'destroy']);
        Route::post('routes/{ulid}/optimize', [AssessorRouteController::class, 'optimize']);
        Route::patch('routes/{ulid}/stops/{stopId}/visit', [AssessorRouteController::class, 'visitStop']);
    });

    // Módulo 03: Atendimentos (CRM)
    Route::prefix('service-requests')->group(function () {
        Route::get('/', [ServiceRequestController::class, 'index']);
        Route::post('/', [ServiceRequestController::class, 'store']);
        Route::get('{ulid}', [ServiceRequestController::class, 'show']);
        Route::put('{ulid}', [ServiceRequestController::class, 'update']);
        Route::delete('{ulid}', [ServiceRequestController::class, 'destroy']);
        Route::patch('{ulid}/status', [ServiceRequestController::class, 'updateStatus']);
        Route::post('{ulid}/updates', [ServiceRequestController::class, 'addUpdate']);
        Route::post('{ulid}/attachments', [ServiceRequestController::class, 'uploadAttachment']);
    });
    Route::get('service-categories', [ServiceCategoryController::class, 'index']);
    Route::post('service-categories', [ServiceCategoryController::class, 'store']);

    // Módulo 04: Comunicação
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::post('/', [CampaignController::class, 'store']);
        Route::get('{ulid}', [CampaignController::class, 'show']);
        Route::put('{ulid}', [CampaignController::class, 'update']);
        Route::delete('{ulid}', [CampaignController::class, 'destroy']);
        Route::post('{ulid}/media', [CampaignController::class, 'uploadMedia']);
        Route::post('{ulid}/send', [CampaignController::class, 'send']);
        Route::post('{ulid}/schedule', [CampaignController::class, 'schedule']);
        Route::post('{ulid}/cancel', [CampaignController::class, 'cancel']);
        Route::get('{ulid}/recipients', [CampaignController::class, 'recipients']);
        Route::post('{ulid}/recipients', [CampaignController::class, 'addRecipient']);
        Route::delete('{ulid}/recipients/{recipientId}', [CampaignController::class, 'removeRecipient']);
    });
    Route::apiResource('message-templates', MessageTemplateController::class)->except(['show']);
    Route::get('communication-config', [CommunicationConfigController::class, 'show']);
    Route::put('communication-config', [CommunicationConfigController::class, 'update']);
    Route::post('communication-config/test', [CommunicationConfigController::class, 'test']);
    Route::apiResource('automation-rules', AutomationRuleController::class)->except(['show']);

    // Módulo 05: Proposições
    Route::prefix('propositions')->group(function () {
        Route::get('stats', [PropositionController::class, 'stats']);
        Route::get('/', [PropositionController::class, 'index']);
        Route::post('/', [PropositionController::class, 'store']);
        Route::get('{ulid}', [PropositionController::class, 'show']);
        Route::put('{ulid}', [PropositionController::class, 'update']);
        Route::delete('{ulid}', [PropositionController::class, 'destroy']);
        Route::patch('{ulid}/status', [PropositionController::class, 'updateStatus']);
        Route::post('{ulid}/publish', [PropositionController::class, 'publish']);
        Route::post('{ulid}/attachments', [PropositionController::class, 'addAttachment']);
    });

    // Módulo 06: Agenda
    Route::prefix('events')->group(function () {
        Route::get('calendar', [EventController::class, 'calendar']);
        Route::get('upcoming', [EventController::class, 'upcoming']);
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('{ulid}', [EventController::class, 'show']);
        Route::put('{ulid}', [EventController::class, 'update']);
        Route::delete('{ulid}', [EventController::class, 'destroy']);
        Route::patch('{ulid}/attendance', [EventController::class, 'recordAttendance']);
        Route::post('{ulid}/attendees', [EventController::class, 'addAttendee']);
    });

    // Módulo 07: Financeiro
    Route::prefix('expenses')->group(function () {
        Route::get('summary', [ExpenseController::class, 'summary']);
        Route::get('export', [ExpenseController::class, 'export']);
        Route::get('budget', [BudgetController::class, 'show']);
        Route::put('budget', [BudgetController::class, 'update']);
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::get('{ulid}', [ExpenseController::class, 'show']);
        Route::put('{ulid}', [ExpenseController::class, 'update']);
        Route::delete('{ulid}', [ExpenseController::class, 'destroy']);
        Route::patch('{ulid}/status', [ExpenseController::class, 'changeStatus']);
        Route::patch('{ulid}/approve', [ExpenseController::class, 'approve']);
        Route::post('{ulid}/documents', [ExpenseController::class, 'uploadDocument']);
        Route::get('{ulid}/documents/{docUlid}/download', [ExpenseController::class, 'downloadDocument']);
        Route::delete('{ulid}/documents/{docUlid}', [ExpenseController::class, 'deleteDocument']);
    });
    Route::apiResource('donors', DonorController::class)->except(['show', 'update']);
    Route::get('budget-allowance', [BudgetController::class, 'show']);
    Route::put('budget-allowance', [BudgetController::class, 'update']);

    // Módulo 08: Documentos
    Route::prefix('documents')->group(function () {
        // Folders must come before {ulid} wildcard
        Route::get('folders', [DocumentFolderController::class, 'index']);
        Route::post('folders', [DocumentFolderController::class, 'store']);
        Route::delete('folders/{id}', [DocumentFolderController::class, 'destroy']);

        Route::post('generate', [DocumentController::class, 'generate']);
        Route::get('/', [DocumentController::class, 'index']);
        Route::post('/', [DocumentController::class, 'store']);
        Route::get('{ulid}', [DocumentController::class, 'show']);
        Route::put('{ulid}', [DocumentController::class, 'update']);
        Route::delete('{ulid}', [DocumentController::class, 'destroy']);
        Route::get('{ulid}/download', [DocumentController::class, 'download']);
        Route::get('{ulid}/versions', [DocumentController::class, 'versions']);
    });

    // Módulo: Formulários
    Route::prefix('forms')->group(function () {
        Route::get('/', [FormController::class, 'index']);
        Route::post('/', [FormController::class, 'store']);
        Route::get('{ulid}', [FormController::class, 'show']);
        Route::put('{ulid}', [FormController::class, 'update']);
        Route::delete('{ulid}', [FormController::class, 'destroy']);
        Route::put('{ulid}/fields', [FormController::class, 'updateFields']);
        Route::get('{ulid}/submissions', [FormController::class, 'submissions']);
        Route::get('{ulid}/embed-code', [FormController::class, 'embedCode']);
    });

    // Módulo 09: Dashboard e Relatórios
    Route::prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardController::class, 'overview']);
        Route::get('contacts', [DashboardController::class, 'contacts']);
        Route::get('service-requests', [DashboardController::class, 'serviceRequests']);
        Route::get('campaigns', [DashboardController::class, 'campaigns']);
        Route::get('propositions', [DashboardController::class, 'propositions']);
        Route::get('team', [DashboardController::class, 'team']);
    });
});
