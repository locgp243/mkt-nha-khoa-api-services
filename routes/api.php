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
 * @var App\Controllers\Public\PublicCategoryController $publicCategoryCtrl
 */

// --- ADMIN AUTH ---
$router->post('/api/admin/login', [$adminAuthCtrl, 'login']);
$router->get('/api/admin/me', $authMiddleware, [$adminAuthCtrl, 'me']);

// --- QUẢN LÝ DANH MỤC (CATEGORIES) ---
$router->get('/api/admin/categories', $authMiddleware, [$categoryCtrl, 'index']);
$router->post('/api/admin/categories', $authMiddleware, [$categoryCtrl, 'store']);

//PUBLIC
$router->get('/api/public/categories', [$publicCategoryCtrl, 'index']);
$router->get('/api/public/categories/{slug}', [$publicCategoryCtrl, 'show']);

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
$router->get('/api/public/posts/{slug}', [$publicPostCtrl, 'show']);

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

// --- QUẢN LÝ BẢNG GIÁ (PRICING PACKAGES) ---
$router->get('/api/admin/pricing-packages', $authMiddleware, [$pricingPackageCtrl, 'index']);
$router->post('/api/admin/pricing-packages', $authMiddleware, [$pricingPackageCtrl, 'store']);
$router->get('/api/admin/pricing-packages/{id}', $authMiddleware, [$pricingPackageCtrl, 'show']);
$router->put('/api/admin/pricing-packages/{id}', $authMiddleware, [$pricingPackageCtrl, 'update']);
$router->delete('/api/admin/pricing-packages/{id}', $authMiddleware, [$pricingPackageCtrl, 'destroy']);

// PUBLIC
$router->get('/api/public/pricing-packages', [$publicPricingPackageCtrl, 'index']);
$router->get('/api/public/pricing-packages/{id}', [$publicPricingPackageCtrl, 'show']);


// --- QUẢN LÝ TRANG TĨNH (STATIC PAGES) ---

// --- ADMIN ROUTES ---
$router->get('/api/admin/static-pages', $authMiddleware, [$adminStaticPageCtrl, 'index']);
$router->post('/api/admin/static-pages', $authMiddleware, [$adminStaticPageCtrl, 'store']);
$router->get('/api/admin/static-pages/{id}', $authMiddleware, [$adminStaticPageCtrl, 'show']);
$router->put('/api/admin/static-pages/{id}', $authMiddleware, [$adminStaticPageCtrl, 'update']);
$router->delete('/api/admin/static-pages/{id}', $authMiddleware, [$adminStaticPageCtrl, 'destroy']);

// --- PUBLIC ROUTES ---
$router->get('/api/public/pages/{slug}', [$publicStaticPageCtrl, 'show']);

// --- QUẢN LÝ KHÁCH HÀNG (CUSTOMERS) --- // <<-- THÊM MỚI
$router->get('/api/admin/customers', $authMiddleware, [$customerCtrl, 'index']);
$router->post('/api/admin/customers', $authMiddleware, [$customerCtrl, 'store']);
$router->get('/api/admin/customers/{id}', $authMiddleware, [$customerCtrl, 'show']);
$router->put('/api/admin/customers/{id}', $authMiddleware, [$customerCtrl, 'update']);
$router->delete('/api/admin/customers/{id}', $authMiddleware, [$customerCtrl, 'destroy']);

//Public
// === PUBLIC ROUTES ===
// -- MỚI: Route để khách hàng đăng ký --
$router->post('/api/public/customers/register', [$publicCustomerCtrl, 'register']);
// -- CŨ: Route để lấy thông tin công khai --
$router->get('/api/public/customers/{code}', [$publicCustomerCtrl, 'show']);

// ...
// --- QUẢN LÝ NGƯỜI DÙNG (ADMINS/USERS) ---
$router->get('/api/admin/users', $authMiddleware, [$adminCtrl, 'index']);
$router->post('/api/admin/users', $authMiddleware, [$adminCtrl, 'store']);
$router->get('/api/admin/users/{id}', $authMiddleware, [$adminCtrl, 'show']);
$router->put('/api/admin/users/{id}', $authMiddleware, [$adminCtrl, 'update']);
$router->delete('/api/admin/users/{id}', $authMiddleware, [$adminCtrl, 'destroy']);
$router->post('/api/admin/users/{id}/restore', $authMiddleware, [$adminCtrl, 'restore']); // <<-- Đảm bảo đã có route này

// --- QUẢN LÝ CONTACT ---
////Admin
$router->get('/api/admin/contacts', $authMiddleware, [$adminContactCtrl, 'index']);
$router->get('/api/admin/contacts/{id}', $authMiddleware, [$adminContactCtrl, 'show']);
$router->put('/api/admin/contacts/{id}', $authMiddleware, [$adminContactCtrl, 'update']);
$router->delete('/api/admin/contacts/{id}', $authMiddleware, [$adminContactCtrl, 'destroy']);

////Public
$router->post('/api/public/contact', [$publicContactCtrl, 'store']);


// --- QUẢN LÝ CÀI ĐẶT TRANG (SITE SETTINGS) ---

// --- PUBLIC ROUTE ---
$router->get('/api/public/settings', [$publicSettingCtrl, 'index']);

// --- ADMIN ROUTES ---
$router->get('/api/admin/settings', $authMiddleware, [$settingCtrl, 'index']);
$router->put('/api/admin/settings', $authMiddleware, [$settingCtrl, 'update']);
// ...

//// Public OTP
$router->post('/api/send-otp', [$publicAuthCtrl, 'handlePhoneNumberVerification']);
$router->post('/api/verify-otp', [$publicAuthCtrl, 'verifyOtp']);

// --- NHẬT KÝ HOẠT ĐỘNG ---
$router->get('/api/admin/activity-logs', $authMiddleware, [$activityLogCtrl, 'index']);

// --- ADMIN UPLOAD ---
$router->post('/api/admin/upload', $authMiddleware, [$uploadCtrl, 'upload']);