<?php

declare(strict_types=1);


// ---- Paths & base URL -------------------------------------------------
define('APP_ROOT', dirname(__DIR__));

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base = str_replace('\\', '/', dirname($scriptName));
define('BASE_URL', rtrim($base, '/'));

// ---- Autoloader (App\ -> app/) ---------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/Core/helpers.php';

use App\Config\Env;
use App\Controllers\AuthController;
use App\Controllers\CouponController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\InventoryController;
use App\Controllers\PosController;
use App\Controllers\PricingController;
use App\Controllers\ReportController;
use App\Controllers\RepairBoardController;
use App\Controllers\SalesHistoryController;
use App\Controllers\SupplierController;
use App\Controllers\TechnicianController;
use App\Controllers\UserController;
use App\Core\App;

// ---- Bootstrap --------------------------------------------------------
Env::load(APP_ROOT . '/config/.env');

session_start();

// ---- Routes -----------------------------------------------------------
$app = new App();

$app->get('/', [AuthController::class, 'showLogin']);
$app->get('/login', [AuthController::class, 'showLogin']);
$app->post('/login', [AuthController::class, 'login']);
$app->get('/logout', [AuthController::class, 'logout']);
$app->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$app->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$app->get('/reset-password', [AuthController::class, 'showResetPassword']);
$app->post('/reset-password', [AuthController::class, 'resetPassword']);
$app->get('/admin/dashboard', [DashboardController::class, 'index']);

$app->get('/admin/users', [UserController::class, 'index']);
$app->get('/admin/users/create', [UserController::class, 'create']);
$app->post('/admin/users/create', [UserController::class, 'create']);
$app->get('/admin/users/edit/{id}', [UserController::class, 'edit']);
$app->post('/admin/users/update/{id}', [UserController::class, 'update']);
$app->post('/admin/users/toggle/{id}', [UserController::class, 'toggleStatus']);

$app->get('/admin/inventory', [InventoryController::class, 'index']);
$app->get('/admin/inventory/create', [InventoryController::class, 'create']);
$app->post('/admin/inventory/create', [InventoryController::class, 'create']);
$app->get('/admin/inventory/edit/{id}', [InventoryController::class, 'edit']);
$app->post('/admin/inventory/update/{id}', [InventoryController::class, 'update']);
$app->post('/admin/inventory/delete/{id}', [InventoryController::class, 'delete']);

$app->get('/admin/suppliers', [SupplierController::class, 'index']);
$app->get('/admin/suppliers/create', [SupplierController::class, 'create']);
$app->post('/admin/suppliers/create', [SupplierController::class, 'create']);
$app->get('/admin/suppliers/edit/{id}', [SupplierController::class, 'edit']);
$app->post('/admin/suppliers/update/{id}', [SupplierController::class, 'update']);
$app->post('/admin/suppliers/delete/{id}', [SupplierController::class, 'delete']);

$app->get('/sales/pos', [PosController::class, 'pos']);
$app->get('/sales/pos/search', [PosController::class, 'searchProducts']);
$app->post('/sales/pos/cart/add', [PosController::class, 'cartAdd']);
$app->post('/sales/pos/cart/update', [PosController::class, 'cartUpdate']);
$app->post('/sales/pos/cart/remove', [PosController::class, 'cartRemove']);
$app->get('/sales/pos/cart', [PosController::class, 'cartGet']);
$app->post('/sales/pos/coupon', [PosController::class, 'applyCoupon']);
$app->post('/sales/pos/process', [PosController::class, 'processPayment']);
$app->get('/sales/history', [SalesHistoryController::class, 'index']);
$app->get('/sales/history/view/{id}', [SalesHistoryController::class, 'show']);
$app->get('/sales/customers', [CustomerController::class, 'index']);
$app->get('/sales/customers/create', [CustomerController::class, 'create']);
$app->post('/sales/customers/create', [CustomerController::class, 'create']);
$app->get('/sales/customers/edit/{id}', [CustomerController::class, 'edit']);
$app->post('/sales/customers/update/{id}', [CustomerController::class, 'update']);
$app->post('/sales/customers/delete/{id}', [CustomerController::class, 'delete']);
$app->get('/sales/customers/view/{id}', [CustomerController::class, 'show']);
$app->get('/sales/customers/search', [CustomerController::class, 'search']);
$app->post('/sales/customers/quick-create', [CustomerController::class, 'quickCreate']);
$app->post('/sales/customers/set', [CustomerController::class, 'setCustomer']);
$app->get('/technician/board', [RepairBoardController::class, 'index']);
$app->get('/technician/board/create', [RepairBoardController::class, 'showCreateJob']);
$app->post('/technician/board/create', [RepairBoardController::class, 'createJob']);
$app->get('/technician/board/search-customer', [RepairBoardController::class, 'searchCustomer']);
$app->post('/technician/board/move', [RepairBoardController::class, 'move']);
$app->post('/technician/board/note', [RepairBoardController::class, 'note']);
$app->get('/technician/board/view/{id}', [RepairBoardController::class, 'viewJob']);
$app->post('/technician/board/update', [RepairBoardController::class, 'updateJob']);
$app->post('/technician/board/complete', [RepairBoardController::class, 'markComplete']);
$app->post('/technician/board/notify', [RepairBoardController::class, 'notifyCustomer']);
$app->get('/technician/my-tasks', [RepairBoardController::class, 'myTasks']);
$app->get('/technician/all-jobs', [RepairBoardController::class, 'allJobs']);

$app->get('/admin/reports', [ReportController::class, 'index']);
$app->get('/admin/reports/export-csv', [ReportController::class, 'exportCsv']);

$app->get('/admin/pricing', [PricingController::class, 'index']);
$app->post('/admin/pricing/update-price', [PricingController::class, 'updatePrice']);

$app->get('/admin/coupons', [CouponController::class, 'index']);
$app->get('/admin/coupons/create', [CouponController::class, 'create']);
$app->post('/admin/coupons/create', [CouponController::class, 'create']);
$app->get('/admin/coupons/edit/{id}', [CouponController::class, 'edit']);
$app->post('/admin/coupons/update/{id}', [CouponController::class, 'update']);
$app->post('/admin/coupons/delete/{id}', [CouponController::class, 'delete']);

$app->run();
