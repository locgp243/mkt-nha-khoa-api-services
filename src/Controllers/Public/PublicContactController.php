<?php
// src/Controllers/Public/ContactController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\Contact;
use App\Models\Notification;

class PublicContactController
{
    private Contact $contactModel;
    private Notification $notificationModel;

    public function __construct(Contact $contactModel, Notification $notificationModel)
    {
        $this->contactModel = $contactModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Lưu thông tin liên hệ từ form (POST /api/public/contact)
     */
    public function store(Request $request): Response
    {
        $data = $request->getBody();

        if (empty($data['full_name']) || empty($data['email']) || empty($data['message'])) {
            return new Response(['message' => 'Họ tên, email và nội dung tin nhắn là bắt buộc.'], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new Response(['message' => 'Địa chỉ email không hợp lệ.'], 400);
        }

        $newContactId = $this->contactModel->create($data);

        if ($newContactId) {
            // Gửi thông báo cho admin (giả sử admin ID = 1 là super admin)
            $this->notificationModel->create(
                1,
                'new_contact',
                "Bạn có một liên hệ mới từ: {$data['full_name']}",
                "/contacts/{$newContactId}"
            );
            return new Response(['message' => 'Gửi liên hệ thành công! Chúng tôi sẽ phản hồi sớm nhất có thể.'], 201);
        }

        return new Response(['message' => 'Không thể gửi liên hệ, vui lòng thử lại sau.'], 500);
    }
}