<?php
// src/Core/Request.php

namespace App\Core;
use HTMLPurifier;
use HTMLPurifier_Config;
/**
 * Lớp Request đại diện cho một yêu cầu HTTP.
 *
 * Nó đóng gói và cung cấp quyền truy cập an toàn vào các biến toàn cục
 * của PHP như $_GET, $_POST, $_SERVER, v.v.
 * Điều này giúp mã nguồn trở nên sạch sẽ và dễ kiểm thử hơn.
 */
class Request
{
    protected ?object $user = null;

    public function setUser(object $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?object
    {
        return $this->user;
    }

    /**
     * Lấy đường dẫn URI của yêu cầu (ví dụ: /api/users/1).
     * @return string
     */
    public function getPath(): string
    {
        // strtok() loại bỏ phần query string (?foo=bar) khỏi URI
        $path = strtok($_SERVER['REQUEST_URI'], '?') ?? '/';
        return rtrim($path, '/'); // Loại bỏ dấu / ở cuối để chuẩn hóa
    }

    /**
     * Lấy phương thức của yêu cầu (GET, POST, PUT, DELETE).
     * @return string
     */
    public function getMethod(): string
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Lấy toàn bộ phần body của request.
     * Thường dùng cho các request POST, PUT, PATCH với dữ liệu JSON.
     * @return array
     */
    public function getBody(): array
    {
        $body = [];

        if ($this->getMethod() === 'post' || $this->getMethod() === 'put' || $this->getMethod() === 'patch') {
            // Lấy dữ liệu thô từ body của request
            $rawBody = file_get_contents('php://input');
            if ($rawBody) {
                // Giải mã JSON thành mảng kết hợp
                $decodedBody = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decodedBody;
                }
            }

            // Hợp nhất với dữ liệu từ $_POST (dành cho form-data)
            // Dữ liệu từ JSON body sẽ được ưu tiên
            $body = array_merge($_POST, $body);
        }

        // Lấy dữ liệu từ query string (cho GET request)
        if ($this->getMethod() === 'get') {
            $body = $_GET;
        }

        return $this->sanitize($body);
    }

    /**
     * Lấy giá trị của một header cụ thể.
     * @param string $headerKey Tên của header (ví dụ: 'Authorization').
     * @return string|null
     */
    public function getHeader(string $headerKey): ?string
    {
        $headers = getallheaders();
        return $headers[$headerKey] ?? null;
    }

    /**
     * Làm sạch dữ liệu đầu vào để tăng cường bảo mật.
     * @param mixed $data Dữ liệu cần làm sạch.
     * @return mixed
     */
    private function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            // Đệ quy để làm sạch từng phần tử trong mảng
            return array_map([$this, 'sanitize'], $data);
        }
        // Thêm dòng kiểm tra này
        if (is_null($data)) {
            return null; // Trả về null nếu đầu vào là null
        }

        // **Sử dụng HTML Purifier**
        $config = HTMLPurifier_Config::createDefault();

        // Tùy chọn: bạn có thể cấu hình thêm tại đây nếu muốn
        // Ví dụ: cho phép nhúng video YouTube
        // $config->set('HTML.SafeIframe', true);
        // $config->set('URI.SafeIframeRegexp', '%^(https?://)?(www\.youtube(?:-nocookie)?\.com/embed/)%');

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($data);
    }
}