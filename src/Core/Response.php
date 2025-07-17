<?php
// src/Core/Response.php

namespace App\Core;

/**
 * Lớp Response đại diện cho một phản hồi HTTP sẽ được gửi về cho client.
 *
 * Chuẩn hóa việc gửi đi các response JSON, bao gồm status code, headers và body.
 */
class Response
{
    protected array $headers = [];
    protected mixed $content;
    protected int $statusCode;

    /**
     * @param mixed $content Nội dung của response, thường là một mảng hoặc đối tượng.
     * @param int $statusCode Mã trạng thái HTTP (ví dụ: 200, 404, 500).
     * @param array $headers Các header bổ sung.
     */
    public function __construct(mixed $content, int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Thiết lập một header cho response.
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Gửi response về cho client.
     *
     * Phương thức này sẽ thiết lập các header, status code và echo ra nội dung đã
     * được mã hóa dưới dạng JSON, sau đó kết thúc script.
     */
    public function send(): void
    {
        // --- Xóa các header đã được thiết lập trước đó (nếu có) ---
        // header_remove(); // Cân nhắc sử dụng nếu gặp vấn đề về header conflict

        // --- Thiết lập Status Code ---
        http_response_code($this->statusCode);

        // --- Thiết lập các Headers mặc định và tùy chỉnh ---
        // Luôn trả về JSON
        header('Content-Type: application/json; charset=utf-8');
        // Các header CORS (có thể chuyển vào một middleware sau này)
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }

        // --- Gửi nội dung (body) ---
        $jsonContent = json_encode($this->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonContent === false) {
            // Xử lý lỗi nếu không thể mã hóa JSON
            http_response_code(500);
            $jsonContent = json_encode([
                'error' => 'JSON Encoding Error',
                'message' => 'Lỗi khi tạo phản hồi từ server.',
            ]);
        }

        echo $jsonContent;

        // Kết thúc script để đảm bảo không có gì khác được gửi đi
        exit;
    }
}