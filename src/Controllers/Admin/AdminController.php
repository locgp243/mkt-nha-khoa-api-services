<?php
// src/Controllers/Admin/AdminController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\Notification;

class AdminController
{
    private Admin $adminModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(Admin $adminModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->adminModel = $adminModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Lấy danh sách người dùng (GET /api/admin/users)
     */
    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $users = $this->adminModel->getAll($options);
        $total = $this->adminModel->getTotalCount($options);

        return new Response([
            'data' => $users,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => ($options['limit'] ?? 10) > 0 ? ceil($total / ($options['limit'] ?? 10)) : 0,
            ]
        ]);
    }

    /**
     * Lấy chi tiết một người dùng (GET /api/admin/users/{id})
     */
    public function show(Request $request, int $id): Response
    {
        $user = $this->adminModel->findById($id);

        if (!$user) {
            return new Response(['message' => 'Người dùng không tồn tại.'], 404);
        }
        unset($user['password_hash']); // Luôn xóa password hash khỏi response
        return new Response(['data' => $user]);
    }

    /**
     * Tạo người dùng mới (POST /api/admin/users)
     */
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $currentUser = $request->getUser();

        // Validation cơ bản
        if (empty($data['full_name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            return new Response(['message' => 'Vui lòng điền đầy đủ các trường bắt buộc.'], 400);
        }

        if ($this->adminModel->emailExists($data['email'])) {
            return new Response(['message' => 'Địa chỉ email này đã được sử dụng.'], 409);
        }

        $newUserId = $this->adminModel->create($data);

        if ($newUserId) {
            $this->activityLogModel->create($currentUser->admin_id, 'created', 'admin_user', $newUserId, ['email' => $data['email']]);
            $this->notificationModel->create($currentUser->admin_id, 'new_user_created', "Tài khoản mới đã được tạo: {$data['email']}");
            return new Response(['message' => 'Tạo người dùng thành công!', 'id' => $newUserId], 201);
        }

        return new Response(['message' => 'Không thể tạo người dùng.'], 500);
    }

    /**
     * Cập nhật người dùng (PUT /api/admin/users/{id})
     */
    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $currentUser = $request->getUser();

        $user = $this->adminModel->findById($id);
        if (!$user) {
            return new Response(['message' => 'Người dùng không tồn tại.'], 404);
        }

        // Không cho phép user tự thay đổi vai trò hoặc trạng thái của chính mình
        if ($currentUser->admin_id === $id && (isset($data['role']) && $data['role'] !== $user['role'] || isset($data['status']) && $data['status'] !== $user['status'])) {
            return new Response(['message' => 'Bạn không thể tự thay đổi vai trò hoặc trạng thái của chính mình.'], 403);
        }

        if (!empty($data['email']) && $data['email'] !== $user['email'] && $this->adminModel->emailExists($data['email'], $id)) {
            return new Response(['message' => 'Địa chỉ email này đã được sử dụng.'], 409);
        }

        if ($this->adminModel->update($id, $data)) {
            $this->activityLogModel->create($currentUser->admin_id, 'updated', 'admin_user', $id, ['email' => $data['email'] ?? $user['email']]);
            $this->notificationModel->create($currentUser->admin_id, 'user_updated', "Thông tin tài khoản '{$user['email']}' đã được cập nhật.");
            return new Response(['message' => 'Cập nhật người dùng thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật người dùng.'], 500);
    }

    /**
     * Xóa mềm người dùng (DELETE /api/admin/users/{id})
     */
    public function destroy(Request $request, int $id): Response
    {
        $currentUser = $request->getUser();

        if ($currentUser->admin_id === $id) {
            return new Response(['message' => 'Bạn không thể tự xóa chính mình.'], 403);
        }

        $user = $this->adminModel->findById($id);
        if (!$user) {
            return new Response(['message' => 'Người dùng không tồn tại.'], 404);
        }

        if ($this->adminModel->delete($id)) {
            $this->activityLogModel->create($currentUser->admin_id, 'soft_deleted', 'admin_user', $id, ['email' => $user['email']]);
            return new Response(['message' => 'Đã vô hiệu hóa người dùng.']);
        }

        return new Response(['message' => 'Không thể xóa người dùng.'], 500);
    }
}