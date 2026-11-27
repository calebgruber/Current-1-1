<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AssetController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CategoryController;
use App\Controllers\ContactController;
use App\Controllers\ContainerController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\ItemController;
use App\Controllers\LocationController;
use App\Controllers\PortalController;
use App\Controllers\ScanController;
use App\Controllers\SetupController;
use App\Controllers\ShowController;
use App\Controllers\ShowWarehouseController;
use App\Controllers\TemplateController;
use App\Controllers\WarehouseController;
use App\Core\Router;

return static function (Router $r): void {
    // First-run setup wizard (only works while the app is not installed).
    $r->get('/setup', [SetupController::class, 'show'], ['auth' => false, 'setup' => true]);
    $r->post('/setup', [SetupController::class, 'store'], ['auth' => false, 'setup' => true]);

    // Sign in
    $r->get('/login', [AuthController::class, 'show'], ['auth' => false, 'guest' => true]);
    $r->post('/login', [AuthController::class, 'login'], ['auth' => false, 'guest' => true]);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Account & preferences
    $r->get('/account', [AccountController::class, 'show']);
    $r->post('/account', [AccountController::class, 'update']);
    $r->post('/account/password', [AccountController::class, 'password']);
    $r->post('/account/preferences', [AccountController::class, 'preferences']);

    // Dashboard & search
    $r->get('/', [DashboardController::class, 'index']);
    $r->get('/search', [DashboardController::class, 'search']);

    // Shows
    $r->get('/shows', [ShowController::class, 'index']);
    $r->get('/shows/create', [ShowController::class, 'create'], ['can' => 'shows.manage']);
    $r->post('/shows', [ShowController::class, 'store'], ['can' => 'shows.manage']);
    $r->get('/shows/{id}', [ShowController::class, 'show']);
    $r->get('/shows/{id}/edit', [ShowController::class, 'edit'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}', [ShowController::class, 'update'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}/delete', [ShowController::class, 'destroy'], ['can' => 'delete']);
    $r->post('/shows/{id}/status', [ShowController::class, 'status'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}/share', [ShowController::class, 'share'], ['can' => 'shows.manage']);
    $r->get('/shows/{id}/items', [ShowController::class, 'items']);
    $r->post('/shows/{id}/items', [ShowController::class, 'addItem'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}/items/{line}', [ShowController::class, 'updateItem'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}/items/{line}/delete', [ShowController::class, 'removeItem'], ['can' => 'shows.manage']);
    $r->get('/shows/{id}/team', [ShowController::class, 'team']);
    $r->post('/shows/{id}/team', [ShowController::class, 'addContact'], ['can' => 'shows.manage']);
    $r->post('/shows/{id}/team/{contact}/delete', [ShowController::class, 'removeContact'], ['can' => 'shows.manage']);
    $r->get('/shows/{id}/messages', [ShowController::class, 'messages']);
    $r->post('/shows/{id}/messages', [ShowController::class, 'postMessage'], ['can' => 'messages']);
    $r->get('/shows/{id}/documents', [DocumentController::class, 'forShow']);
    $r->post('/shows/{id}/documents', [DocumentController::class, 'generate'], ['can' => 'documents.generate']);

    // Show warehouse workflow: prep into containers, check out, return
    $r->get('/shows/{id}/prep', [ShowWarehouseController::class, 'prep']);
    $r->post('/shows/{id}/prep', [ShowWarehouseController::class, 'prepScan'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/prep/item', [ShowWarehouseController::class, 'prepPick'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/prep/{checkout}/delete', [ShowWarehouseController::class, 'unprep'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/containers', [ShowWarehouseController::class, 'addContainer'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/containers/{sc}', [ShowWarehouseController::class, 'updateContainer'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/containers/{sc}/delete', [ShowWarehouseController::class, 'removeContainer'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/checkout', [ShowWarehouseController::class, 'checkout'], ['can' => 'warehouse']);
    $r->get('/shows/{id}/returns', [ShowWarehouseController::class, 'returns']);
    $r->post('/shows/{id}/returns', [ShowWarehouseController::class, 'returnScan'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/returns/{checkout}', [ShowWarehouseController::class, 'returnLine'], ['can' => 'warehouse']);
    $r->post('/shows/{id}/returns/containers/{sc}', [ShowWarehouseController::class, 'returnContainer'], ['can' => 'warehouse']);

    // Inventory
    $r->get('/items', [ItemController::class, 'index']);
    $r->get('/items/create', [ItemController::class, 'create'], ['can' => 'inventory.manage']);
    $r->post('/items', [ItemController::class, 'store'], ['can' => 'inventory.manage']);
    $r->get('/items/{id}', [ItemController::class, 'show']);
    $r->get('/items/{id}/edit', [ItemController::class, 'edit'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}', [ItemController::class, 'update'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}/delete', [ItemController::class, 'destroy'], ['can' => 'delete']);
    $r->get('/items/{id}/photo', [ItemController::class, 'photo']);
    $r->post('/items/{id}/photo', [ItemController::class, 'uploadPhoto'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}/photo/delete', [ItemController::class, 'deletePhoto'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}/accessories', [ItemController::class, 'addAccessory'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}/accessories/{accessory}/delete', [ItemController::class, 'removeAccessory'], ['can' => 'inventory.manage']);
    $r->post('/items/{id}/assets', [AssetController::class, 'store'], ['can' => 'inventory.manage']);

    $r->get('/assets', [AssetController::class, 'index']);
    $r->get('/assets/{id}', [AssetController::class, 'show']);
    $r->post('/assets/{id}', [AssetController::class, 'update'], ['can' => 'inventory.manage']);
    $r->post('/assets/{id}/delete', [AssetController::class, 'destroy'], ['can' => 'delete']);

    $r->get('/categories', [CategoryController::class, 'index']);
    $r->post('/categories', [CategoryController::class, 'store'], ['can' => 'inventory.manage']);
    $r->post('/categories/{id}', [CategoryController::class, 'update'], ['can' => 'inventory.manage']);
    $r->post('/categories/{id}/delete', [CategoryController::class, 'destroy'], ['can' => 'delete']);

    $r->get('/locations', [LocationController::class, 'index']);
    $r->post('/locations', [LocationController::class, 'store'], ['can' => 'inventory.manage']);
    $r->get('/locations/{id}', [LocationController::class, 'show']);
    $r->post('/locations/{id}', [LocationController::class, 'update'], ['can' => 'inventory.manage']);
    $r->post('/locations/{id}/delete', [LocationController::class, 'destroy'], ['can' => 'delete']);

    $r->get('/containers', [ContainerController::class, 'index']);
    $r->post('/containers', [ContainerController::class, 'store'], ['can' => 'inventory.manage']);
    $r->get('/containers/{id}', [ContainerController::class, 'show']);
    $r->post('/containers/{id}', [ContainerController::class, 'update'], ['can' => 'inventory.manage']);
    $r->post('/containers/{id}/delete', [ContainerController::class, 'destroy'], ['can' => 'delete']);

    // Warehouse
    $r->get('/scan', [ScanController::class, 'index']);
    $r->get('/scan/station', [ScanController::class, 'station']);
    $r->get('/api/scan', [ScanController::class, 'lookup']);
    $r->get('/warehouse/out', [WarehouseController::class, 'out']);
    $r->get('/warehouse/repairs', [WarehouseController::class, 'repairs']);

    // Calendar
    $r->get('/calendar', [CalendarController::class, 'index']);

    // Paperwork
    $r->get('/documents', [DocumentController::class, 'index']);
    $r->get('/documents/{id}', [DocumentController::class, 'show']);
    $r->get('/documents/{id}/print', [DocumentController::class, 'print']);
    $r->post('/documents/{id}/share', [DocumentController::class, 'share'], ['can' => 'documents.generate']);
    $r->post('/documents/{id}/delete', [DocumentController::class, 'destroy'], ['can' => 'delete']);
    $r->get('/templates', [TemplateController::class, 'index'], ['can' => 'templates.manage']);
    $r->get('/templates/{id}', [TemplateController::class, 'edit'], ['can' => 'templates.manage']);
    $r->post('/templates/{id}', [TemplateController::class, 'update'], ['can' => 'templates.manage']);
    $r->post('/templates/{id}/preview', [TemplateController::class, 'preview'], ['can' => 'templates.manage']);
    $r->post('/templates/{id}/reset', [TemplateController::class, 'reset'], ['can' => 'templates.manage']);

    // Contacts
    $r->get('/contacts', [ContactController::class, 'index']);
    $r->get('/contacts/create', [ContactController::class, 'create'], ['can' => 'contacts.manage']);
    $r->post('/contacts', [ContactController::class, 'store'], ['can' => 'contacts.manage']);
    $r->get('/contacts/{id}', [ContactController::class, 'show']);
    $r->post('/contacts/{id}', [ContactController::class, 'update'], ['can' => 'contacts.manage']);
    $r->post('/contacts/{id}/delete', [ContactController::class, 'destroy'], ['can' => 'delete']);

    // Admin
    $r->get('/admin/users', [AdminController::class, 'users'], ['can' => 'admin']);
    $r->post('/admin/users', [AdminController::class, 'storeUser'], ['can' => 'admin']);
    $r->post('/admin/users/{id}', [AdminController::class, 'updateUser'], ['can' => 'admin']);
    $r->get('/admin/settings', [AdminController::class, 'settings'], ['can' => 'admin']);
    $r->post('/admin/settings', [AdminController::class, 'saveSettings'], ['can' => 'admin']);
    $r->get('/admin/database', [AdminController::class, 'database'], ['can' => 'admin']);
    $r->post('/admin/database/migrate', [AdminController::class, 'migrate'], ['can' => 'admin']);
    $r->post('/admin/database/templates', [AdminController::class, 'seedTemplates'], ['can' => 'admin']);
    $r->post('/admin/database/demo', [AdminController::class, 'demo'], ['can' => 'admin']);

    // Show portal: the production team's private link for messages and shared paperwork.
    $r->get('/portal/{token}', [PortalController::class, 'show'], ['auth' => false, 'public' => true]);
    $r->post('/portal/{token}/messages', [PortalController::class, 'message'], ['auth' => false, 'public' => true]);
    $r->get('/portal/{token}/documents/{id}', [PortalController::class, 'document'], ['auth' => false, 'public' => true]);
};
