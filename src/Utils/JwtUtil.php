<?php
// src/Utils/JwtUtil.php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * Lớp tiện ích để tạo và xác thực JSON Web Tokens (JWT).
 * Cấu hình được nạp từ bên ngoài để tăng cường bảo mật và linh hoạt.
 */
class JwtUtil
{
    private string $secretKey;
    private string $algorithm;
    private int $expirationMinutes;

    /**
     * @param array $config Mảng cấu hình JWT từ config/app.php
     */
    public function __construct(array $config)
    {
        $this->secretKey = $config['secret'];
        $this->algorithm = $config['algo'];
        $this->expirationMinutes = $config['expiration'];
    }

    /**
     * Tạo một JWT mới.
     * @param array $payload Dữ liệu sẽ được mã hóa vào token.
     * @return string Chuỗi token.
     */
    public function generateToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + ($this->expirationMinutes * 60);

        $data = array_merge($payload, [
            'iat' => $issuedAt,       // Issued at: Thời điểm token được cấp
            'exp' => $expire,         // Expire: Thời điểm hết hạn
            'iss' => 'dental-crm-api', // Issuer: Đơn vị cấp token
        ]);

        return JWT::encode($data, $this->secretKey, $this->algorithm);
    }

    /**
     * Giải mã và xác thực một JWT.
     * @param string $jwt Token cần giải mã.
     * @return object|null Dữ liệu (payload) của token nếu hợp lệ, null nếu không.
     * @throws Exception Nếu token không hợp lệ (hết hạn, sai chữ ký,...).
     */
    public function decodeToken(string $jwt): ?object
    {
        // Lớp Router sẽ bắt Exception này và trả về response lỗi phù hợp
        return JWT::decode($jwt, new Key($this->secretKey, $this->algorithm));
    }
}