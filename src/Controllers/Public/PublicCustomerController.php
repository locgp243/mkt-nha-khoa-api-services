<?php
// src/Controllers/Public/PublicCustomerController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;

class PublicCustomerController
{
    private Customer $customerModel;

    public function __construct(Customer $customerModel)
    {
        $this->customerModel = $customerModel;
    }

    /**
     * Lấy chi tiết một khách hàng bằng mã khách hàng (GET /api/public/customers/{code})
     */
    public function show(Request $request, string $code): Response
    {
        $customer = $this->customerModel->findByCustomerCode($code);
        if (!$customer) {
            return new Response(['message' => 'Thông tin khách hàng không tồn tại hoặc đã bị khóa.'], 404);
        }

        // Loại bỏ các thông tin nhạy cảm trước khi trả về
        unset($customer['password_hash']);
        unset($customer['id']); // Không nên lộ ID database

        return new Response(['data' => $customer]);
    }

    /**
     * Xử lý yêu cầu đăng ký tài khoản mới của khách hàng (POST /api/public/customers/register)
     * @param Request $request
     * @return Response
     */
    public function register(Request $request): Response
    {
        $data = $request->getBody();

        // --- Validation đầu vào ---
        if (empty($data['referring_doctor_1']) || empty($data['email']) || empty($data['password'])) {
            return new Response(['message' => 'Tên bác sĩ giới thiệu, email và mật khẩu là bắt buộc.'], 400);
        }

        if (strlen($data['password']) < 6) {
            return new Response(['message' => 'Mật khẩu phải có ít nhất 6 ký tự.'], 400);
        }

        if ($this->customerModel->emailExists($data['email'])) {
            return new Response(['message' => 'Địa chỉ email này đã được sử dụng.'], 409); // 409 Conflict
        }

        // --- Tạo khách hàng ---
        $newCustomerId = $this->customerModel->create($data);

        if ($newCustomerId) {
            // Lấy thông tin khách hàng vừa tạo để trả về
            $newCustomer = $this->customerModel->findById($newCustomerId);
            unset($newCustomer['password_hash']); // Luôn xóa password
            unset($newCustomer['id']);

            return new Response([
                'message' => 'Đăng ký tài khoản thành công!',
                'data' => $newCustomer
            ], 201); // 201 Created
        }

        return new Response(['message' => 'Đã có lỗi xảy ra trong quá trình đăng ký.'], 500);
    }
}