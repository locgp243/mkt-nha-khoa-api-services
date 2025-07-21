<?php
// public/index.php

// Khai báo các lớp sẽ sử dụng để dễ đọc hơn
use App\Core\{Request, Router};
use App\Utils\{JwtUtil, FileUploader, SmsService}; // THÊM SmsService
use App\Middleware\AuthMiddleware;
<<<<<<< HEAD
<<<<<<< HEAD
use App\Models\{Admin, Category, Post, Customer, SiteSetting, ActivityLog, Notification, PricingPackage, Otp}; // THÊM Otp
use App\Controllers\Admin\{AdminAuthController, CategoryController, PostController, CustomerController, SettingController, UploadController, PricingPackageController};
use App\Controllers\Public\{PublicPostController, PublicCategoryController, PublicPricingPackageController, PublicAuthController};
=======
=======
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)
use App\Models\{
    Admin,
    Category,
    Post,
    Customer,
    SiteSetting,
    ActivityLog,
    Notification,
    PricingPackage,
    StaticPage
};
use App\Controllers\Admin\{
    AdminAuthController,
    CategoryController,
    PostController,
    CustomerController,
    SettingController,
    UploadController,
    PricingPackageController,
    StaticPageController
};
use App\Controllers\Public\{
    PublicPostController,
    PublicCategoryController,
    PublicPricingPackageController,
    PublicStaticPageController
};
// --- KHỞI TẠO & CẤU HÌNH BAN ĐẦU ---
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)

// --- KHỞI TẠO & CẤU HÌNH BAN ĐẦU ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tải Autoloader và các file cấu hình
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// --- XỬ LÝ CORS ---
// Thầy khuyến khích em dùng logic kiểm tra môi trường mà em đã viết sẵn
$allowedOrigin = $config['app']['env'] === 'development'
    ? '*'
    : ($_ENV['FRONTEND_URL'] ?? 'http://your-production-frontend.com'); // Nhớ thay đổi domain production

header("Access-Control-Allow-Origin: {$allowedOrigin}");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Xử lý pre-flight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- THIẾT LẬP XỬ LÝ LỖI ---
if (isset($config['app']['env']) && $config['app']['env'] === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- KHỞI TẠO CÁC DỊCH VỤ CỐT LÕI (DEPENDENCY INJECTION) ---
// Thứ tự khởi tạo rất quan trọng: Config -> DB -> Utils/Models -> Middleware -> Controllers

// 1. Kết nối Cơ sở dữ liệu
$dbConnection = getDbConnection($config['database']);

// 2. Khởi tạo các Utilities
$jwtUtil = new JwtUtil($config['jwt']);
$fileUploader = new FileUploader();
$smsService = new SmsService(); // THÊM VÀO

// 3. Khởi tạo các Models
$adminModel = new Admin($dbConnection);
$categoryModel = new Category($dbConnection);
$postModel = new Post($dbConnection);
$customerModel = new Customer($dbConnection);
$settingModel = new SiteSetting($dbConnection);
$activityLogModel = new ActivityLog($dbConnection);
$notificationModel = new Notification($dbConnection);
$pricingPackageModel = new PricingPackage($dbConnection);
<<<<<<< HEAD
<<<<<<< HEAD
$otpModel = new Otp($dbConnection); // THÊM VÀO
=======
$staticPageModel = new StaticPage($dbConnection);
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)
=======
$staticPageModel = new StaticPage($dbConnection);
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)

// 4. Khởi tạo Middleware
$authMiddleware = new AuthMiddleware($jwtUtil);

// 5. Khởi tạo các Controllers (và inject dependencies vào chúng)
// ADMIN
$adminAuthCtrl = new AdminAuthController($adminModel, $jwtUtil);
$categoryCtrl = new CategoryController($categoryModel, $activityLogModel, $notificationModel);
$postCtrl = new PostController($postModel, $activityLogModel, $notificationModel);
$pricingPackageCtrl = new PricingPackageController($pricingPackageModel, $activityLogModel, $notificationModel);
<<<<<<< HEAD
<<<<<<< HEAD
=======
=======
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)
$adminStaticPageCtrl = new StaticPageController($staticPageModel, $activityLogModel, $notificationModel);

//// PUBLIC
$publicPostCtrl = new PublicPostController($postModel, $activityLogModel, $notificationModel);
$publicCategoryCtrl = new PublicCategoryController($categoryModel, $activityLogModel, $notificationModel);
$publicPricingPackageCtrl = new PublicPricingPackageController($pricingPackageModel);
$publicStaticPageCtrl = new PublicStaticPageController($staticPageModel);

// $customerCtrl = new CustomerController($customerModel);
// $settingCtrl = new SettingController($settingModel);
>>>>>>> 30058ae (feat: Thêm api trang tĩnh, cả admin, public)
$uploadCtrl = new UploadController($fileUploader);

// PUBLIC
$publicPostCtrl = new PublicPostController($postModel); // Giả định chỉ cần PostModel
$publicCategoryCtrl = new PublicCategoryController($categoryModel); // Giả định chỉ cần CategoryModel
$publicPricingPackageCtrl = new PublicPricingPackageController($pricingPackageModel);
// SỬA LẠI DÒNG QUAN TRỌNG DƯỚI ĐÂY
$publicAuthCtrl = new PublicAuthController($otpModel, $smsService);

// --- ROUTING ---
$request = new Request();
$router = new Router($request);

// Nạp các định nghĩa routes.
require_once __DIR__ . '/../routes/api.php';

// Bắt đầu quá trình điều phối
$router->dispatch();