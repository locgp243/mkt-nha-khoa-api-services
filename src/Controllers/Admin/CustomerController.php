<?php
// src/Controllers/Admin/CustomerController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\ActivityLog;
use App\Models\Notification; // <<-- Đảm bảo đã use Notification

class CustomerController
{
    private Customer $customerModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel; // <<-- Thêm thuộc tính

    // Cập nhật constructor để nhận NotificationModel
    public function __construct(Customer $customerModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->customerModel = $customerModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel; // <<-- Gán giá trị
    }

    /**
     * Lấy danh sách khách hàng (GET /api/admin/customers)
     */
    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $customers = $this->customerModel->getAll($options);
        $total = $this->customerModel->getTotalCount($options);

        return new Response([
            'data' => $customers,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => ($options['limit'] ?? 10) > 0 ? ceil($total / ($options['limit'] ?? 10)) : 0,
            ]
        ]);
    }

    /**
     * Lấy chi tiết một khách hàng (GET /api/admin/customers/{id})
     */
    public function show(Request $request, int $id): Response
    {
        $customer = $this->customerModel->findById($id);
        if (!$customer) {
            return new Response(['message' => 'Khách hàng không tồn tại.'], 404);
        }
        return new Response(['data' => $customer]);
    }

    /**
     * Tạo một khách hàng mới (POST /api/admin/customers)
     */
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        if (empty($data['referring_doctor_1']) || empty($data['email'])) {
            return new Response(['message' => 'Tên bác sĩ giới thiệu và email là bắt buộc.'], 400);
        }

        if ($this->customerModel->emailExists($data['email'])) {
            return new Response(['message' => 'Địa chỉ email này đã được sử dụng.'], 409);
        }

        $newCustomerId = $this->customerModel->create($data);

        if ($newCustomerId) {
            $this->activityLogModel->create($admin->admin_id, 'created', 'customer', $newCustomerId, ['email' => $data['email']]);
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'new_customer',
                "Khách hàng mới đã được tạo: {$data['email']}",
                "/customers/{$newCustomerId}"
            );
            return new Response(['message' => 'Tạo khách hàng thành công!', 'id' => $newCustomerId], 201);
        }

        return new Response(['message' => 'Không thể tạo khách hàng.'], 500);
    }

    /**
     * Cập nhật thông tin khách hàng (PUT /api/admin/customers/{id})
     */
    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        $customer = $this->customerModel->findById($id);
        if (!$customer) {
            return new Response(['message' => 'Khách hàng không tồn tại.'], 404);
        }

        if (!empty($data['email']) && $data['email'] !== $customer['email'] && $this->customerModel->emailExists($data['email'], $id)) {
            return new Response(['message' => 'Địa chỉ email này đã được sử dụng.'], 409);
        }

        if ($this->customerModel->update($id, $data)) {
            $this->activityLogModel->create($admin->admin_id, 'updated', 'customer', $id, ['email' => $data['email'] ?? $customer['email']]);
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'customer_updated',
                "Thông tin khách hàng '{$customer['email']}' đã được cập nhật.",
                "/customers/{$id}"
            );
            return new Response(['message' => 'Cập nhật khách hàng thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật khách hàng.'], 500);
    }

    /**
     * Xóa mềm một khách hàng (DELETE /api/admin/customers/{id})
     */
    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $customer = $this->customerModel->findById($id);
        if (!$customer) {
            return new Response(['message' => 'Khách hàng không tồn tại.'], 404);
        }

        if ($this->customerModel->delete($id)) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'soft_deleted',
                'customer',
                $id,
                ['email' => $customer['email']]
            );
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'customer_deleted',
                "Khách hàng '{$customer['email']}' đã được xóa."
            );
            return new Response(['message' => 'Đã xóa khách hàng.']);
        }

        return new Response(['message' => 'Không thể xóa khách hàng.'], 500);
    }
}