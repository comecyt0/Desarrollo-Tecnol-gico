<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Solicitudes\SolicitudController;
use App\Http\Controllers\Solicitudes\RevisionController;
use App\Http\Controllers\Convocatorias\ConvocatoriaController;
use App\Http\Controllers\Evaluaciones\EvaluadorController;
use App\Http\Controllers\Catalogos\CatalogoController;
use App\Http\Controllers\Catalogos\ProgramaCatalogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TipoProgramaController;
use App\Http\Controllers\Admin\ProgramaCampoController;
use App\Http\Controllers\Admin\ProgramaDocumentoController;
use App\Http\Controllers\Admin\ProgramaRubroController;
use App\Http\Controllers\Admin\ProgramaEtapaController;
use App\Http\Controllers\Admin\ProgramaModalidadController;
use App\Http\Controllers\Admin\ProgramaCriterioEvaluacionController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\ListaNegraController;
use App\Http\Controllers\NotificacionLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\InstitucionController;
use App\Http\Controllers\MinistracionController;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\ApiGatewayMiddleware;
use App\Http\Middleware\CircuitBreakerMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Middleware Global de Seguridad para todas las APIS
Route::middleware([
    ApiGatewayMiddleware::class,
    CircuitBreakerMiddleware::class,
    RateLimitMiddleware::class
])->group(function () {

    // === AUTH ===
    Route::group(['prefix' => 'auth'], function () {
        Route::post('login', [AuthController::class, 'login']);
        // Route::post('register', [AuthController::class, 'register']);
    });

    // === PUBLIC CATALOGS (No authentication required) ===
    Route::group(['prefix' => 'catalogs/programa'], function () {
        Route::get('{tipo_programa_id}/campos', [ProgramaCatalogController::class, 'campos']);
        Route::get('{tipo_programa_id}/documentos', [ProgramaCatalogController::class, 'documentos']);
        Route::get('{tipo_programa_id}/criterios', [ProgramaCatalogController::class, 'criterios']);
        Route::get('{tipo_programa_id}/rubros', [ProgramaCatalogController::class, 'rubros']);
        Route::get('{tipo_programa_id}/etapas', [ProgramaCatalogController::class, 'etapas']);
        Route::get('{tipo_programa_id}/modalidades', [ProgramaCatalogController::class, 'modalidades']);
        Route::get('{tipo_programa_id}', [ProgramaCatalogController::class, 'show']);
    });

    // === PROTECTED ROUTES ===
    Route::group(['middleware' => 'api.auth'], function () {

        Route::group(['prefix' => 'auth'], function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Agrupación por Roles (Ejemplo inicial, los controllers completos se implementan en fase 2)

        // ADMIN COMECYT (1)
        Route::group(['prefix' => 'admin', 'middleware' => 'admin'], function () {
            Route::get('stats', [DashboardController::class, 'adminStats']);
            Route::get('activity', [DashboardController::class, 'adminActivity']);
            Route::get('alerts', [DashboardController::class, 'adminAlerts']);

            // PROGRAMAS DINÁMICOS CRUD
            Route::get('programas', [TipoProgramaController::class, 'index']);
            Route::post('programas', [TipoProgramaController::class, 'store']);
            Route::put('programas/{tipoPrograma}', [TipoProgramaController::class, 'update']);
            Route::delete('programas/{tipoPrograma}', [TipoProgramaController::class, 'destroy']);

            // Campos dinámicos por programa
            Route::get('programas/{tipoPrograma}/campos', [ProgramaCampoController::class, 'index']);
            Route::post('programas/{tipoPrograma}/campos', [ProgramaCampoController::class, 'store']);
            Route::put('programas/{tipoPrograma}/campos/{campo}', [ProgramaCampoController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/campos/{campo}', [ProgramaCampoController::class, 'destroy']);

            // Documentos por programa
            Route::get('programas/{tipoPrograma}/documentos', [ProgramaDocumentoController::class, 'index']);
            Route::post('programas/{tipoPrograma}/documentos', [ProgramaDocumentoController::class, 'store']);
            Route::put('programas/{tipoPrograma}/documentos/{documento}', [ProgramaDocumentoController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/documentos/{documento}', [ProgramaDocumentoController::class, 'destroy']);

            // Rubros por programa
            Route::get('programas/{tipoPrograma}/rubros', [ProgramaRubroController::class, 'index']);
            Route::post('programas/{tipoPrograma}/rubros', [ProgramaRubroController::class, 'store']);
            Route::put('programas/{tipoPrograma}/rubros/{rubro}', [ProgramaRubroController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/rubros/{rubro}', [ProgramaRubroController::class, 'destroy']);

            // Etapas por programa
            Route::get('programas/{tipoPrograma}/etapas', [ProgramaEtapaController::class, 'index']);
            Route::post('programas/{tipoPrograma}/etapas', [ProgramaEtapaController::class, 'store']);
            Route::put('programas/{tipoPrograma}/etapas/{etapa}', [ProgramaEtapaController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/etapas/{etapa}', [ProgramaEtapaController::class, 'destroy']);

            // Modalidades por programa
            Route::get('programas/{tipoPrograma}/modalidades', [ProgramaModalidadController::class, 'index']);
            Route::post('programas/{tipoPrograma}/modalidades', [ProgramaModalidadController::class, 'store']);
            Route::put('programas/{tipoPrograma}/modalidades/{modalidad}', [ProgramaModalidadController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/modalidades/{modalidad}', [ProgramaModalidadController::class, 'destroy']);

            // Criterios de evaluación por programa
            Route::get('programas/{tipoPrograma}/criterios', [ProgramaCriterioEvaluacionController::class, 'index']);
            Route::post('programas/{tipoPrograma}/criterios', [ProgramaCriterioEvaluacionController::class, 'store']);
            Route::put('programas/{tipoPrograma}/criterios/{criterio}', [ProgramaCriterioEvaluacionController::class, 'update']);
            Route::delete('programas/{tipoPrograma}/criterios/{criterio}', [ProgramaCriterioEvaluacionController::class, 'destroy']);

            Route::apiResource('convocatorias', ConvocatoriaController::class);
            Route::apiResource('users', UserController::class);
            Route::apiResource('ministraciones', MinistracionController::class);
            Route::apiResource('informes', InformeController::class);
            Route::apiResource('instituciones', InstitucionController::class);
            Route::apiResource('lista-negra', ListaNegraController::class);
            Route::apiResource('notificaciones', NotificacionLogController::class)->only(['index', 'show']);
            Route::post('notificaciones/{notificacion}/leer', [NotificacionLogController::class, 'marcarLeida']);
            Route::post('notificaciones/leer-todas', [NotificacionLogController::class, 'marcarTodasLeidas']);
            // Asignación de Evaluadores
            Route::post('asignaciones-evaluador', [\App\Http\Controllers\EvaluadorController::class, 'asignar']);
            Route::delete('asignaciones-evaluador/{asignacion}', [\App\Http\Controllers\EvaluadorController::class, 'desasignar']);
        });

        // REVISOR (2)
        Route::group(['prefix' => 'revisor', 'middleware' => 'revisor'], function () {
            Route::get('stats', [DashboardController::class, 'revisorStats']);
            Route::get('solicitudes/pendientes', [RevisionController::class, 'pendientes']);
            Route::get('solicitudes/{solicitud}', [RevisionController::class, 'show']);
            Route::post('solicitudes/{solicitud}/aprobar', [RevisionController::class, 'approve']);
            Route::post('solicitudes/{solicitud}/observar', [RevisionController::class, 'observe']);
            Route::post('solicitudes/{solicitud}/aprobar-informe', [RevisionController::class, 'approveInforme']);
        });

        // EVALUADOR (3)
        Route::group(['prefix' => 'evaluador', 'middleware' => 'evaluador'], function () {
            Route::get('stats', [DashboardController::class, 'evaluadorStats']);
            Route::get('asignaciones', [EvaluadorController::class, 'asignaciones']);
            Route::get('asignaciones/{asignacion}', [EvaluadorController::class, 'show']);
            Route::put('asignaciones/{asignacion}/iniciar-evaluacion', [EvaluadorController::class, 'startEvaluation']);
            Route::post('asignaciones/{asignacion}/dictamen', [EvaluadorController::class, 'saveDictamen']);
        });

        // CATALOGOS GENERALES (Todos los autenticados)
        Route::get('catalogos', [CatalogoController::class, 'index']);

        // CATALOGS - Cache management (requires authentication/admin)
        Route::delete('catalogs/programa/{tipo_programa_id}/cache', [ProgramaCatalogController::class, 'clearCache']);

        // SOLICITANTE INSTITUCIONAL (4)
        Route::group(['prefix' => 'solicitudes'], function () {
            Route::get('/', [SolicitudController::class, 'index']);
            Route::post('/', [SolicitudController::class, 'store']);
            Route::get('convocatorias-activas', [SolicitudController::class, 'activeConvocatorias']);
            Route::get('{solicitud}', [SolicitudController::class, 'show']);
            Route::post('{solicitud}/enviar', [SolicitudController::class, 'enviar']);
            Route::post('{solicitud}/reenviar', [SolicitudController::class, 'reenviar']);
            Route::post('{solicitud}/informe', [SolicitudController::class, 'submitInforme']);
            Route::post('{solicitud}/documentos', [\App\Http\Controllers\DocumentoUploadController::class, 'upload']);
            Route::delete('{solicitud}/documentos/{documento}', [\App\Http\Controllers\DocumentoUploadController::class, 'destroy']);
        });

        // === DOCUMENTOS Y REPORTES ===
        Route::get('documentos/dictamen/{dictamen}', [DocumentoController::class, 'downloadDictamen']);
        Route::get('documentos/convenio/{solicitud}', [DocumentoController::class, 'downloadConvenio']);
        Route::get('admin/reportes/excel', [DocumentoController::class, 'exportExcel']);
    });
});
