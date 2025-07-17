<?php
// routes/api.php

use App\Core\Response;

/**
 * @var App\Core\Router $router
 * @var App\Controllers\Admin\AdminAuthController $adminAuthCtrl
 */


// --- Route kiểm tra sức khỏe hệ thống (Health Check) ---
$router->get('/api/health-check', function () {
    return new Response([
        'status' => 'OK',
        'message' => 'API is running smoothly!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

/**
 * @var App\Core\Router $router
 * @var App\Middleware\AuthMiddleware $authMiddleware
 * @var App\Controllers\Admin\AdminAuthController $adminAuthCtrl
 * @var App\Controllers\Admin\CategoryController $categoryCtrl
 * @var App\Controllers\Admin\PostController $postCtrl
 * @var App\Controllers\Admin\UploadController $uploadCtrl
 * 
 * @var App\Controllers\Public\PublicPostController $publicPostCtrl

 */

// --- ADMIN AUTH ---
$router->post('/api/admin/login', [$adminAuthCtrl, 'login']);
$router->get('/api/admin/me', $authMiddleware, [$adminAuthCtrl, 'me']);

// --- QUẢN LÝ DANH MỤC (CATEGORIES) ---
$router->get('/api/admin/categories', $authMiddleware, [$categoryCtrl, 'index']);
$router->post('/api/admin/categories', $authMiddleware, [$categoryCtrl, 'store']);

// Các route cho hành động hàng loạt (bulk actions)
$router->post('/api/admin/categories/bulk-delete', $authMiddleware, [$categoryCtrl, 'bulkDelete']);
$router->post('/api/admin/categories/bulk-restore', $authMiddleware, [$categoryCtrl, 'bulkRestore']);
$router->post('/api/admin/categories/bulk-force-delete', $authMiddleware, [$categoryCtrl, 'bulkForceDelete']);

// Các route cho một tài nguyên cụ thể (theo ID)
$router->get('/api/admin/categories/{id}', $authMiddleware, [$categoryCtrl, 'show']);
$router->put('/api/admin/categories/{id}', $authMiddleware, [$categoryCtrl, 'update']);
$router->delete('/api/admin/categories/{id}', $authMiddleware, [$categoryCtrl, 'destroy']);
$router->post('/api/admin/categories/{id}/restore', $authMiddleware, [$categoryCtrl, 'restore']);
$router->delete('/api/admin/categories/{id}/force', $authMiddleware, [$categoryCtrl, 'forceDelete']);

// --- QUẢN LÝ BÀI VIẾT (POSTS) ---
$router->get('/api/admin/posts', $authMiddleware, [$postCtrl, 'index']);
$router->post('/api/admin/posts', $authMiddleware, [$postCtrl, 'store']);

// PUBLIC
$router->get('/api/public/posts', [$publicPostCtrl, 'index']);
$router->get('/api/public/posts/{id}', [$publicPostCtrl, 'show']);

// Bulk Actions
$router->post('/api/admin/posts/bulk-delete', $authMiddleware, [$postCtrl, 'bulkDelete']);
$router->post('/api/admin/posts/bulk-restore', $authMiddleware, [$postCtrl, 'bulkRestore']);
$router->post('/api/admin/posts/bulk-force-delete', $authMiddleware, [$postCtrl, 'bulkForceDelete']);

// Single Resource Actions
$router->get('/api/admin/posts/{id}', $authMiddleware, [$postCtrl, 'show']);
$router->put('/api/admin/posts/{id}', $authMiddleware, [$postCtrl, 'update']);
$router->delete('/api/admin/posts/{id}', $authMiddleware, [$postCtrl, 'destroy']);
$router->post('/api/admin/posts/{id}/restore', $authMiddleware, [$postCtrl, 'restore']);
$router->delete('/api/admin/posts/{id}/force', $authMiddleware, [$postCtrl, 'forceDelete']);


// // --- ADMIN CUSTOMERS ---
// $router->get('/api/admin/customers', $authMiddleware, [$customerCtrl, 'index']);
// $router->put('/api/admin/customers/{id}/status', $authMiddleware, [$customerCtrl, 'updateStatus']);

// // --- ADMIN SETTINGS ---
// $router->get('/api/admin/settings', $authMiddleware, [$settingCtrl, 'index']);
// $router->put('/api/admin/settings', $authMiddleware, [$settingCtrl, 'update']);

// --- ADMIN UPLOAD ---
$router->post('/api/admin/upload', $authMiddleware, [$uploadCtrl, 'upload']);