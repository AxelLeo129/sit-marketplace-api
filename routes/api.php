<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthEventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\PresentationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Rutas públicas. Limitadas por minuto para frenar fuerza bruta y abuso de correo.
*/
// "login": por IP y por cuenta + IP (ver AppServiceProvider::configureRateLimits).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Perfil del usuario autenticado.
    Route::get('/me', [ProfileController::class, 'show']);
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword']);
    Route::post('/me/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/me/clinic-logo', [ProfileController::class, 'updateClinicLogo']);

    // Usuarios: administrador y visitador (permisos en UserPolicy).
    Route::apiResource('users', UserController::class);

    // Recetas: el médico las emite, el call-center las gestiona (permisos en PrescriptionPolicy).
    Route::apiResource('prescriptions', PrescriptionController::class)->except('destroy');
    Route::post('/prescriptions/{prescription}/reschedule', [PrescriptionController::class, 'reschedule']);
    Route::post('/prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel']);
    Route::post('/prescriptions/{prescription}/send', [PrescriptionController::class, 'send'])->middleware('throttle:5,1');

    // Clientes y direcciones: call-center y administrador.
    Route::middleware('can:manage-customers')->group(function () {
        Route::apiResource('customers', CustomerController::class)->except('destroy');
        // scopeBindings: la dirección debe pertenecer al cliente de la URL (si no, 404).
        Route::scopeBindings()->group(function () {
            Route::apiResource('customers.addresses', CustomerAddressController::class)->only(['store', 'update', 'destroy']);
        });
    });

    // Pedidos y su flujo facturación → despacho → entrega (permisos en OrderPolicy).
    Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/invoice', [OrderController::class, 'invoice']);
    Route::post('/orders/{order}/assign', [OrderController::class, 'assign']);
    Route::post('/orders/{order}/start-delivery', [OrderController::class, 'startDelivery']);
    Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver']);
    Route::post('/orders/{order}/fail-delivery', [OrderController::class, 'failDelivery']);
    Route::get('/couriers', [CourierController::class, 'index'])->middleware('can:dispatch-orders');

    // Seguridad: registro de accesos, solo el administrador.
    Route::get('/security/auth-events', [AuthEventController::class, 'index'])->middleware('can:view-security');

    // Reportes: el administrador ve todo; quien cobra comisión, solo las suyas.
    Route::get('/reports/my-commissions', [ReportController::class, 'myCommissions'])->middleware('can:earn-commissions');
    Route::middleware('can:view-financials')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/commissions', [ReportController::class, 'commissions']);
    });

    // Catálogo: todos los usuarios lo consultan, solo el administrador lo modifica.
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('labs', LabController::class)->only(['index', 'show']);
    Route::apiResource('presentations', PresentationController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    Route::middleware('can:manage-catalog')->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::apiResource('labs', LabController::class)->except(['index', 'show']);
        Route::apiResource('presentations', PresentationController::class)->except(['index', 'show']);
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::post('/products/{product}/image', [ProductImageController::class, 'store']);
        Route::delete('/products/{product}/image', [ProductImageController::class, 'destroy']);
    });
});
