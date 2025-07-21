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

        // Cân nhắc loại bỏ các thông tin nhạy cảm không muốn public
        // unset($customer['...']); 

        return new Response(['data' => $customer]);
    }
}