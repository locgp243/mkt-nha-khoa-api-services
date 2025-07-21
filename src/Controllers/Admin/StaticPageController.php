<?php
// src/Controllers/Admin/StaticPageController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\StaticPage;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Utils\StringUtil;

class StaticPageController
{
    private StaticPage $staticPageModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(StaticPage $staticPageModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->staticPageModel = $staticPageModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $pages = $this->staticPageModel->getAll($options);
        $total = $this->staticPageModel->getTotalCount($options);

        return new Response([
            'data' => $pages,
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
        $page = $this->staticPageModel->findById($id);
        if (!$page) {
            return new Response(['message' => 'Trang tĩnh không tồn tại.'], 404);
        }
        return new Response(['data' => $page]);
    }

    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        if (empty($data['title']) || empty($data['content'])) {
            return new Response(['message' => 'Tiêu đề và nội dung là bắt buộc.'], 400);
        }

        $slug = StringUtil::generateSlug($data['slug'] ?? $data['title']);
        if ($this->staticPageModel->slugExists($slug)) {
            return new Response(['message' => 'Đường dẫn (slug) này đã tồn tại.'], 409);
        }

        $data['admin_id'] = $admin->admin_id;
        $newPageId = $this->staticPageModel->create($data);

        if ($newPageId) {
            $this->activityLogModel->create($admin->admin_id, 'created', 'static_page', $newPageId, ['title' => $data['title']]);
            $this->notificationModel->create($admin->admin_id, 'new_static_page', "Trang tĩnh mới đã được tạo: '{$data['title']}'");
            return new Response(['message' => 'Tạo trang tĩnh thành công!', 'id' => $newPageId], 201);
        }

        return new Response(['message' => 'Không thể tạo trang tĩnh.'], 500);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        $existingPage = $this->staticPageModel->findById($id);
        if (!$existingPage) {
            return new Response(['message' => 'Trang tĩnh không tồn tại.'], 404);
        }

        if (empty($data['title']) || empty($data['content'])) {
            return new Response(['message' => 'Tiêu đề và nội dung là bắt buộc.'], 400);
        }

        $slug = StringUtil::generateSlug($data['slug'] ?? $data['title']);
        if ($this->staticPageModel->slugExists($slug, $id)) {
            return new Response(['message' => 'Đường dẫn (slug) này đã tồn tại.'], 409);
        }

        $data['admin_id'] = $admin->admin_id;
        if ($this->staticPageModel->update($id, $data)) {
            $this->activityLogModel->create($admin->admin_id, 'updated', 'static_page', $id, ['title' => $data['title']]);
            $this->notificationModel->create($admin->admin_id, 'static_page_updated', "Trang tĩnh '{$data['title']}' đã được cập nhật.");
            return new Response(['message' => 'Cập nhật trang tĩnh thành công!']);
        }

        return new Response(['message' => 'Không thể cập nhật trang tĩnh.'], 500);
    }

    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $page = $this->staticPageModel->findById($id);

        if (!$page) {
            return new Response(['message' => 'Trang tĩnh không tồn tại.'], 404);
        }

        if ($this->staticPageModel->delete($id)) {
            $this->activityLogModel->create($admin->admin_id, 'deleted', 'static_page', $id, ['title' => $page['title']]);
            $this->notificationModel->create($admin->admin_id, 'static_page_deleted', "Trang tĩnh '{$page['title']}' đã được xóa mềm.");
            return new Response(['message' => 'Xóa trang tĩnh thành công.']);
        }

        return new Response(['message' => 'Không thể xóa trang tĩnh.'], 500);
    }
}