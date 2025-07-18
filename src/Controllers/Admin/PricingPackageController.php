<?php
// src/Controllers/Admin/PricingPackageController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\PricingPackage;
use App\Models\ActivityLog;
use App\Models\Notification;


class PricingPackageController
{
    private PricingPackage $packageModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;


    public function __construct(PricingPackage $packageModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->packageModel = $packageModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $packages = $this->packageModel->getAll($options);
        $total = $this->packageModel->getTotalCount($options);

        return new Response([
            'data' => $packages,
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
        $package = $this->packageModel->findById($id);
        if (!$package) {
            return new Response(['message' => 'Gói giá không tồn tại.'], 404);
        }
        return new Response(['data' => $package]);
    }

    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();
        $data['admin_id'] = $admin->admin_id;

        // Validation
        if (empty($data['name']) || !isset($data['price_monthly']) || !is_array($data['features'])) {
            return new Response(['message' => 'Tên, giá hàng tháng và các tính năng là bắt buộc.'], 400);
        }

        $newId = $this->packageModel->create($data);

        if ($newId) {
            $this->activityLogModel->create($admin->admin_id, 'created', 'pricing_package', $newId, ['name' => $data['name']]);
            $this->notificationModel->create($admin->admin_id, 'new_pricing_package', "Gói giá mới đã được tạo: '{$data['name']}'");
            return new Response(['message' => 'Tạo gói giá thành công!', 'id' => $newId], 201);
        }

        return new Response(['message' => 'Không thể tạo gói giá.'], 500);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();
        $data['admin_id'] = $admin->admin_id;

        $existingPackage = $this->packageModel->findById($id);
        if (!$existingPackage) {
            return new Response(['message' => 'Gói giá không tồn tại.'], 404);
        }

        if ($this->packageModel->update($id, $data)) {
            $this->activityLogModel->create($admin->admin_id, 'updated', 'pricing_package', $id, ['name' => $data['name']]);
            $this->notificationModel->create($admin->admin_id, 'pricing_package_updated', "Gói giá '{$data['name']}' đã được cập nhật.");
            return new Response(['message' => 'Cập nhật gói giá thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật gói giá.'], 500);
    }

    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $package = $this->packageModel->findById($id);

        if (!$package) {
            return new Response(['message' => 'Gói giá không tồn tại.'], 404);
        }

        if ($this->packageModel->delete($id)) {
            $this->activityLogModel->create($admin->admin_id, 'deleted', 'pricing_package', $id, ['name' => $package['name']]);
            $this->notificationModel->create($admin->admin_id, 'pricing_package_deleted', "Gói giá '{$package['name']}' đã được xóa vĩnh viễn.");
            return new Response(['message' => 'Xóa gói giá thành công.']);
        }

        return new Response(['message' => 'Không thể xóa gói giá.'], 500);
    }
}