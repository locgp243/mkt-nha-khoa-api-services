<?php
// src/Controllers/Admin/SettingController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\SiteSetting;
use App\Models\ActivityLog;
use App\Models\Notification;

class SettingController
{
    private SiteSetting $siteSettingModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(SiteSetting $siteSettingModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->siteSettingModel = $siteSettingModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Lấy tất cả cài đặt (GET /api/admin/settings)
     */
    public function index(Request $request): Response
    {
        $settings = $this->siteSettingModel->getAllSettings();
        return new Response(['data' => $settings]);
    }

    /**
     * Cập nhật các cài đặt (PUT /api/admin/settings)
     */
    public function update(Request $request): Response
    {
        $settingsData = $request->getBody()['settings'] ?? null;
        $admin = $request->getUser();

        if (!is_array($settingsData) || empty($settingsData)) {
            return new Response(['message' => 'Dữ liệu cài đặt không hợp lệ. Vui lòng gửi một mảng `settings`.'], 400);
        }

        if ($this->siteSettingModel->updateSettings($settingsData)) {
            $this->activityLogModel->create($admin->admin_id, 'updated', 'site_settings', null, ['data' => $settingsData]);
            $this->notificationModel->create($admin->admin_id, 'settings_updated', 'Cài đặt chung của trang web đã được cập nhật.');

            return new Response(['message' => 'Cập nhật cài đặt thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật cài đặt.'], 500);
    }
}