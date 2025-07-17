<?php
// config/app.php

// Đảm bảo vendor/autoload.php được require để sử dụng các thư viện từ Composer.
// Đường dẫn __DIR__ . '/../vendor/autoload.php' trỏ từ thư mục /config ra ngoài thư mục gốc rồi vào /vendor.
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Tải các biến môi trường từ file .env ở thư mục gốc của dự án.
 * Dotenv\Dotenv::createImmutable() nhận vào đường dẫn đến thư mục chứa file .env.
 * __DIR__ . '/..' có nghĩa là thư mục cha của thư mục hiện tại (/config), tức là thư mục gốc.
 */
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load(); // Nạp các biến vào $_ENV
    $dotenv->required(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'JWT_SECRET'])->notEmpty();
} catch (Exception $e) {
    // Xử lý trường hợp file .env thiếu hoặc cấu hình quan trọng bị thiếu
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Lỗi cấu hình môi trường.',
        'message' => 'Vui lòng đảm bảo file .env tồn tại và chứa các biến bắt buộc: DB_HOST, DB_DATABASE, DB_USERNAME, JWT_SECRET.',
        'details' => $e->getMessage()
    ]);
    exit;
}


/**
 * Trả về một mảng cấu hình đã được tổ chức.
 * Ứng dụng sẽ sử dụng mảng này thay vì truy cập trực tiếp vào $_ENV.
 * Việc này giúp cấu trúc rõ ràng và dễ dàng thay đổi nguồn cấu hình trong tương lai.
 * Sử dụng toán tử ?? (null coalescing) để cung cấp giá trị mặc định, tránh lỗi.
 */
return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    ],

    'database' => [
        'host' => $_ENV['DB_HOST'],
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'dbname' => $_ENV['DB_DATABASE'],
        'user' => $_ENV['DB_USERNAME'],
        'pass' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4'
    ],

    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'],
        'algo' => $_ENV['JWT_ALGO'] ?? 'HS256',
        'expiration' => (int) ($_ENV['JWT_EXPIRATION_MINUTES'] ?? 60),
    ],

    // Bạn có thể thêm các cấu hình khác ở đây, ví dụ:
    // 'mail' => [ ... ],
    // 'cors' => [ ... ],
];