<?php
// src/Controllers/Admin/CategoryController.php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Utils\StringUtil;

/**
 * Quản lý tất cả các nghiệp vụ CRUD và hành động hàng loạt cho tài nguyên "Category".
 */
class CategoryController
{
    private Category $categoryModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(Category $categoryModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->categoryModel = $categoryModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Lấy danh sách các danh mục (GET /api/admin/categories)
     */
    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $categories = $this->categoryModel->getAll($options);
        $total = $this->categoryModel->getTotalCount($options);

        return new Response([
            'data' => $categories,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => (int) ($options['limit'] ?? 10) > 0 ? ceil($total / (int) ($options['limit'] ?? 10)) : 0
            ]
        ]);
    }

    /**
     * Lấy chi tiết một danh mục (GET /api/admin/categories/{id})
     */
    public function show(Request $request, int $id): Response
    {
        $category = $this->categoryModel->findById($id, true);
        if (!$category) {
            return new Response(['message' => 'Danh mục không tồn tại.'], 404);
        }
        return new Response(['data' => $category]);
    }

    /**
     * Tạo một danh mục mới (POST /api/admin/categories)
     */
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        if (empty($data['name']) || empty($data['category_type'])) {
            return new Response(['message' => 'Tên và loại danh mục là bắt buộc.'], 400);
        }

        $slug = StringUtil::generateSlug($data['slug'] ?? $data['name']);
        if ($this->categoryModel->slugExists($slug)) {
            return new Response(['message' => 'Đường dẫn (slug) này đã tồn tại.'], 409);
        }

        $data['admin_id'] = $admin->admin_id;
        $newId = $this->categoryModel->create($data);

        if ($newId) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'created',
                'category',
                $newId,
                ['name' => $data['name'], 'slug' => $data['slug'], 'category_type' => $data['category_type']]
            );
            $this->notificationModel->create(
                $admin->admin_id,
                'new_category',
                "Đã thêm danh mục mới: '{$data['name']}'",
                "/categories/{$newId}"
            );
            return new Response(['message' => 'Thêm danh mục thành công!', 'id' => $newId], 201);
        }
        return new Response(['message' => 'Không thể thêm danh mục.'], 500);
    }

    /**
     * Cập nhật một danh mục (PUT /api/admin/categories/{id})
     */
    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        $existingCategory = $this->categoryModel->findById($id, true);
        if (!$existingCategory) {
            return new Response(['message' => 'Danh mục không tồn tại.'], 404);
        }

        if (empty($data['name'])) {
            return new Response(['message' => 'Tên danh mục không được để trống.'], 400);
        }

        $slug = StringUtil::generateSlug($data['slug'] ?? $data['name']);
        if ($this->categoryModel->slugExists($slug, $id)) {
            return new Response(['message' => 'Đường dẫn (slug) này đã tồn tại.'], 409);
        }

        $data['admin_id'] = $admin->admin_id;
        if ($this->categoryModel->update($id, $data)) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'updated',
                'category',
                $id,
                ['old_name' => $existingCategory['name'], 'new_name' => $data['name'], 'old_slug' => $existingCategory['slug'], 'new_slug' => $data['slug'] ?? $existingCategory['slug']]
            );
            $this->notificationModel->create(
                $admin->admin_id,
                'category_updated',
                "Đã cập nhật danh mục: '{$data['name']}' (ID: {$id})",
                "/categories/{$id}"
            );
            return new Response(['message' => 'Cập nhật danh mục thành công!']);
        }
        return new Response(['message' => 'Không thể cập nhật danh mục.'], 500);
    }

    /**
     * Xóa mềm một danh mục (DELETE /api/admin/categories/{id})
     */
    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $category = $this->categoryModel->findById($id);
        if (!$category) {
            return new Response(['message' => 'Danh mục không tồn tại hoặc đã bị xóa.'], 404);
        }

        if ($this->categoryModel->delete($id)) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'soft_deleted',
                'category',
                $id,
                ['name' => $category['name']]
            );
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'category_soft_deleted',
                "Đã xóa mềm danh mục: '{$category['name']}' (ID: {$id})",
                "/categories/deleted" // Có thể link đến trang danh mục đã xóa
            );
            return new Response(['message' => 'Đã chuyển danh mục vào thùng rác.']);
        }
        return new Response(['message' => 'Không thể xóa danh mục.'], 500);
    }

    /**
     * Khôi phục một danh mục đã bị xóa mềm (POST /api/admin/categories/{id}/restore)
     */
    public function restore(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $category = $this->categoryModel->findById($id, true);

        if (!$category || $category['deleted_at'] === null) {
            return new Response(['message' => 'Danh mục không tồn tại hoặc chưa bị xóa mềm.'], 404);
        }

        if ($this->categoryModel->restore($id)) {
            // Ghi nhật ký hoạt động
            $this->activityLogModel->create(
                $admin->admin_id,
                'restored',
                'category',
                $id,
                ['name' => $category['name']]
            );
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'category_restored',
                "Đã khôi phục danh mục: '{$category['name']}' (ID: {$id})",
                "/categories/{$id}"
            );
            return new Response(['message' => 'Khôi phục danh mục thành công!']);
        }
        return new Response(['message' => 'Không thể khôi phục danh mục.'], 500);
    }

    /**
     * Xóa vĩnh viễn một danh mục (DELETE /api/admin/categories/{id}/force)
     */
    public function forceDelete(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $category = $this->categoryModel->findById($id, true);

        if (!$category) {
            return new Response(['message' => 'Danh mục không tồn tại.'], 404);
        }

        if ($this->categoryModel->forceDelete($id)) {
            // Ghi nhật ký hoạt động
            $this->activityLogModel->create(
                $admin->admin_id,
                'force_deleted',
                'category',
                $id,
                ['name' => $category['name']]
            );
            // Gửi thông báo
            $this->notificationModel->create(
                $admin->admin_id,
                'category_force_deleted',
                "Đã xóa vĩnh viễn danh mục: '{$category['name']}' (ID: {$id})",
                "/categories/trash" // Có thể link đến thùng rác hoặc trang khác
            );
            return new Response(['message' => 'Đã xóa vĩnh viễn danh mục!']);
        }
        return new Response(['message' => 'Không thể xóa vĩnh viễn danh mục.'], 500);
    }

    /**
     * Xóa mềm nhiều danh mục (POST /api/admin/categories/bulk-delete)
     */
    public function bulkDelete(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->categoryModel->bulkDelete($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create(
                $admin->admin_id,
                'bulk_soft_deleted',
                'category',
                null,
                ['count' => $affectedRows, 'ids' => $ids]
            );
            $this->notificationModel->create(
                $admin->admin_id,
                'categories_bulk_soft_deleted',
                "Đã xóa mềm {$affectedRows} danh mục.",
                "/categories/deleted"
            );
            return new Response(['message' => "Đã xóa mềm thành công {$affectedRows} danh mục."]);
        }
        return new Response(['message' => 'Không có danh mục hợp lệ nào được xóa.'], 400);
    }

    /**
     * Khôi phục nhiều danh mục (POST /api/admin/categories/bulk-restore)
     */
    public function bulkRestore(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->categoryModel->bulkRestore($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create($admin->admin_id, 'bulk_restored', 'category', null, ['count' => $affectedRows, 'ids' => $ids]);
            $this->notificationModel->create($admin->admin_id, 'categories_bulk_restored', "Đã khôi phục {$affectedRows} danh mục.", "/categories");
            return new Response(['message' => "Đã khôi phục thành công {$affectedRows} danh mục."]);
        }
        return new Response(['message' => 'Không có danh mục nào được khôi phục.'], 400);
    }

    /**
     * Xóa vĩnh viễn nhiều danh mục (POST /api/admin/categories/bulk-force-delete)
     */
    public function bulkForceDelete(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->categoryModel->bulkForceDelete($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create($admin->admin_id, 'bulk_force_deleted', 'category', null, ['count' => $affectedRows, 'ids' => $ids]);
            $this->notificationModel->create($admin->admin_id, 'categories_bulk_force_deleted', "Đã xóa vĩnh viễn {$affectedRows} danh mục.", "/categories/trash");
            return new Response(['message' => "Đã xóa vĩnh viễn {$affectedRows} danh mục."]);
        }
        return new Response(['message' => 'Không có danh mục nào được xóa vĩnh viễn.'], 400);
    }
}