<?php
// src/Controllers/Admin/AdminAuthController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Admin;
use App\Utils\JwtUtil;

class AdminAuthController
{
    private Admin $adminModel;
    private JwtUtil $jwtUtil;

    /**
     * Controller nhận các dependency (Admin model, JwtUtil) qua constructor.
     * Đây là nguyên tắc Dependency Injection.
     */
    public function __construct(Admin $adminModel, JwtUtil $jwtUtil)
    {
        $this->adminModel = $adminModel;
        $this->jwtUtil = $jwtUtil;
    }

    /**
     * Xử lý yêu cầu đăng nhập của Admin.
     * @param Request $request Đối tượng chứa thông tin yêu cầu (body, headers, ...).
     * @return Response Đối tượng chứa phản hồi sẽ được gửi về client.
     */
    public function login(Request $request): Response
    {
        $data = $request->getBody();

        // 1. Validation: Kiểm tra dữ liệu đầu vào
        if (empty($data['email']) || empty($data['password'])) {
            return new Response([
                'status' => 'error',
                'message' => 'Vui lòng cung cấp email và mật khẩu.'
            ], 400); // 400 Bad Request
        }

        // 2. Tìm kiếm người dùng
        $admin = $this->adminModel->findByEmail($data['email']);

        // 3. Xác thực
        if (!$admin || !password_verify($data['password'], $admin['password_hash']) || $admin['deleted_at'] !== null) {
            return new Response([
                'status' => 'error',
                'message' => 'Email hoặc mật khẩu không chính xác.'
            ], 401); // 401 Unauthorized
        }

        // 4. Kiểm tra trạng thái tài khoản
        if ($admin['status'] === 'inactive') {
            return new Response([
                'status' => 'error',
                'message' => 'Tài khoản của bạn đã bị vô hiệu hóa.'
            ], 403); // 403 Forbidden
        }

        // 5. Tạo Payload cho Token
        $payload = [
            'admin_id' => $admin['id'],
            'email' => $admin['email'],
            'role' => $admin['role'],
        ];

        // 6. Tạo Token
        $token = $this->jwtUtil->generateToken($payload);

        // 7. Cập nhật lần đăng nhập cuối (không bắt buộc phải thành công)
        $this->adminModel->updateLastLogin($admin['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // 8. Trả về response thành công
        $responseData = [
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'token_type' => 'Bearer',
            'token' => $token,
            'admin' => [
                'id' => $admin['id'],
                'full_name' => $admin['full_name'],
                'email' => $admin['email'],
                'role' => $admin['role'],
            ]
        ];

        return new Response($responseData, 200);
    }

    /**
     * Lấy thông tin admin đang đăng nhập (dựa vào token).
     */
    public function me(Request $request): Response
    {
        // Lấy thông tin user đã được middleware giải mã và gắn vào request
        $user = $request->getUser();

        // Lấy thông tin đầy đủ từ database (tùy chọn)
        $adminDetails = $this->adminModel->findByEmail($user->email);

        if (!$adminDetails) {
            return new Response(['message' => 'Không tìm thấy người dùng.'], 404);
        }

        // Loại bỏ password hash trước khi trả về
        unset($adminDetails['password_hash']);

        return new Response($adminDetails, 200);
    }
}