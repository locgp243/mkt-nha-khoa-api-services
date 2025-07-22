<?php
// src/Controllers/Admin/ContactController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Contact;
use App\Models\ActivityLog;
use App\Models\Notification;

class ContactController
{
    private Contact $contactModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;


    public function __construct(Contact $contactModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->contactModel = $contactModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $contacts = $this->contactModel->getAll($options);
        $total = $this->contactModel->getTotalCount($options);

        return new Response([
            'data' => $contacts,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => ($options['limit'] ?? 10) > 0 ? ceil($total / ($options['limit'] ?? 10)) : 0,
            ]
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $contact = $this->contactModel->findById($id);
        if (!$contact) {
            return new Response(['message' => 'Liên hệ không tồn tại.'], 404);
        }
        return new Response(['data' => $contact]);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();
        $data['admin_id'] = $admin->admin_id;

        $existingContact = $this->contactModel->findById($id);
        if (!$existingContact) {
            return new Response(['message' => 'Liên hệ không tồn tại.'], 404);
        }

        if (empty($data['status'])) {
            return new Response(['message' => 'Trạng thái là bắt buộc.'], 400);
        }

        if ($this->contactModel->update($id, $data)) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'replied',
                'contact',
                $id,
                ['email' => $existingContact['email'], 'status' => $data['status']]
            );
            // Gửi thông báo khi cập nhật trạng thái
            $this->notificationModel->create(
                $admin->admin_id,
                'contact_updated',
                "Liên hệ từ '{$existingContact['email']}' đã được cập nhật trạng thái.",
                "/contacts/{$id}"
            );
            return new Response(['message' => 'Cập nhật liên hệ thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật liên hệ.'], 500);
    }

    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $contact = $this->contactModel->findById($id);
        if (!$contact) {
            return new Response(['message' => 'Liên hệ không tồn tại.'], 404);
        }

        if ($this->contactModel->delete($id)) {
            $this->activityLogModel->create($admin->admin_id, 'deleted', 'contact', $id, ['email' => $contact['email']]);
            $this->notificationModel->create(
                $admin->admin_id,
                'contact_deleted',
                "Đã xóa liên hệ từ '{$contact['email']}'."
            );
            return new Response(['message' => 'Xóa liên hệ thành công.']);
        }

        return new Response(['message' => 'Không thể xóa liên hệ.'], 500);
    }
}