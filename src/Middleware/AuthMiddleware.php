<?php
// src/Middleware/AuthMiddleware.php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Utils\JwtUtil;
// Import các lớp Exception cụ thể của thư viện JWT
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

class AuthMiddleware
{
    private JwtUtil $jwtUtil;

    public function __construct(JwtUtil $jwtUtil)
    {
        $this->jwtUtil = $jwtUtil;
    }

    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->getHeader('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return new Response(['message' => 'Yêu cầu xác thực không hợp lệ. Vui lòng cung cấp Bearer Token.'], 401);
        }

        $token = $matches[1];

        try {
            // Giải mã token
            $decodedToken = $this->jwtUtil->decodeToken($token);

            // Gắn thông tin người dùng đã xác thực vào request
            $request->setUser($decodedToken);

            // Nếu token hợp lệ, gọi middleware/controller tiếp theo.
            // Mọi Exception không phải JWT sẽ được ném ra ngoài cho Router xử lý.
            return $next($request);

        } catch (ExpiredException $e) {
            // Chỉ bắt lỗi token hết hạn
            return new Response(['message' => 'Token đã hết hạn. Vui lòng đăng nhập lại.'], 401);
        } catch (SignatureInvalidException $e) {
            // Chỉ bắt lỗi chữ ký không hợp lệ
            return new Response(['message' => 'Chữ ký token không hợp lệ.'], 401);
        } catch (UnexpectedValueException $e) {
            // Bắt các lỗi khác liên quan đến cấu trúc JWT
            return new Response(['message' => 'Token không hợp lệ.', 'error' => $e->getMessage()], 401);
        }
        // Lưu ý: Chúng ta không còn catch (\Exception $e) chung chung nữa.
    }
}