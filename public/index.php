<?php
// public/index.php

// Khai báo các lớp sẽ sử dụng để dễ đọc hơn
use App\Core\Request;
use App\Core\Router;
use App\Utils\{JwtUtil, FileUploader};
use App\Middleware\AuthMiddleware;
use App\Models\{
    Admin,
    Category,
    Post,
    Customer,
    SiteSetting,
    ActivityLog,
    Notification,
    PricingPackage,
    StaticPage,
    Contact
};
use App\Controllers\Admin\{
    AdminController,
    AdminAuthController,
    CategoryController,
    PostController,
    CustomerController,
    SettingController,
    UploadController,
    PricingPackageController,
    StaticPageController,
    ContactController
};
use App\Controllers\Public\{
    PublicPostController,
    PublicCategoryController,
    PublicPricingPackageController,
    PublicStaticPageController,
    PublicCustomerController,
    PublicContactController
};
// --- KHỞI TẠO & CẤU HÌNH BAN ĐẦU ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tải Autoloader và các file cấu hình
require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// ================== BẮT ĐẦU SỬA LỖI CORS TẠI ĐÂY ==================

// Thiết lập header cho phép origin cụ thể (an toàn hơn cho production)
// Hoặc dùng '*' cho môi trường development.

// $allowedOrigin = $config['app']['env'] === 'development'
//     ? '*'
//     : ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173');

// header("Access-Control-Allow-Origin: {$allowedOrigin}");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // Cache pre-flight request trong 1 ngày

// Xử lý pre-flight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Không cần xử lý gì thêm, chỉ cần trả về status 200 OK
    http_response_code(200);
    exit();
}
// ================== KẾT THÚC SỬA LỖI CORS ==================

// --- THIẾT LẬP XỬ LÝ LỖI ---
if (isset($config['app']['env']) && $config['app']['env'] === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- KHỞI TẠO CÁC DỊCH VỤ CỐT LÕI (DEPENDENCY INJECTION) ---

// 1. Kết nối Cơ sở dữ liệu
$dbConnection = getDbConnection($config['database']);

// 2. Khởi tạo các Utilities (phải có trước khi Controller/Middleware cần)
$jwtUtil = new JwtUtil($config['jwt']); // <--- DÒNG BỊ THIẾU ĐÃ ĐƯỢC THÊM VÀO
$fileUploader = new FileUploader();

// 3. Khởi tạo các Models
$adminModel = new Admin($dbConnection);
$categoryModel = new Category($dbConnection);
$postModel = new Post($dbConnection);
$customerModel = new Customer($dbConnection);
$settingModel = new SiteSetting($dbConnection);
$activityLogModel = new ActivityLog($dbConnection);
$notificationModel = new Notification($dbConnection);
$pricingPackageModel = new PricingPackage($dbConnection);
$staticPageModel = new StaticPage($dbConnection);
$contactModel = new Contact($dbConnection);

//... các model khác

// 4. Khởi tạo Middleware (phải có sau khi đã có các Utils cần thiết)
$authMiddleware = new AuthMiddleware($jwtUtil); // <--- Bây giờ $jwtUtil đã tồn tại

// 5. Khởi tạo các Controllers (và inject dependencies vào chúng)
//// ADMIN
$adminAuthCtrl = new AdminAuthController($adminModel, $jwtUtil);
$categoryCtrl = new CategoryController($categoryModel, $activityLogModel, $notificationModel);
$postCtrl = new PostController($postModel, $activityLogModel, $notificationModel);
$pricingPackageCtrl = new PricingPackageController($pricingPackageModel, $activityLogModel, $notificationModel);
$adminStaticPageCtrl = new StaticPageController($staticPageModel, $activityLogModel, $notificationModel);
$customerCtrl = new CustomerController($customerModel, $activityLogModel, $notificationModel);
$adminCtrl = new AdminController($adminModel, $activityLogModel, $notificationModel);
$adminContactCtrl = new ContactController($contactModel, $activityLogModel, $notificationModel);

//// PUBLIC
$publicPostCtrl = new PublicPostController($postModel, $activityLogModel, $notificationModel);
$publicCategoryCtrl = new PublicCategoryController($categoryModel, $activityLogModel, $notificationModel);
$publicPricingPackageCtrl = new PublicPricingPackageController($pricingPackageModel);
$publicStaticPageCtrl = new PublicStaticPageController($staticPageModel);
$publicCustomerCtrl = new PublicCustomerController($customerModel);
$publicContactCtrl = new PublicContactController($contactModel, $notificationModel);

// $customerCtrl = new CustomerController($customerModel);
// $settingCtrl = new SettingController($settingModel);
$uploadCtrl = new UploadController($fileUploader);
// controlle khác ....

// --- ROUTING ---
$request = new Request();
$router = new Router($request);

// Nạp các định nghĩa routes.
// Các biến $router, $adminAuthCtrl, $authMiddleware sẽ có sẵn để dùng trong file api.php
require_once __DIR__ . '/../routes/api.php';

// Bắt đầu quá trình điều phối
$router->dispatch();