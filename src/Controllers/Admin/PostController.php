<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Utils\StringUtil;

/**
 * Quản lý tất cả các nghiệp vụ cho tài nguyên "Post".
 */
class PostController
{
    private Post $postModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(Post $postModel, ActivityLog $activityLogModel, Notification $notificationModel)
    {
        $this->postModel = $postModel;
        $this->activityLogModel = $activityLogModel;
        $this->notificationModel = $notificationModel;
    }

    /**
     * Lấy danh sách bài viết (GET /api/admin/posts)
     */
    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $posts = $this->postModel->getAll($options);
        $total = $this->postModel->getTotalCount($options);
        return new Response([
            'data' => $posts,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => (int) ($options['limit'] ?? 10) > 0 ? ceil($total / (int) ($options['limit'] ?? 10)) : 0
            ]
        ]);
    }

    /**
     * Lấy chi tiết một bài viết (GET /api/admin/posts/{id})
     */
    public function show(Request $request, int $id): Response
    {
        $post = $this->postModel->findById($id);
        if (!$post) {
            return new Response(['message' => 'Bài viết không tồn tại.'], 404);
        }
        return new Response(['data' => $post]);
    }

    /**
     * Tạo một bài viết mới (POST /api/admin/posts)
     */
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        if (empty($data['title']) || !isset($data['content']) || empty($data['category_id'])) {
            return new Response(['message' => 'Tiêu đề, nội dung và danh mục là bắt buộc.'], 400);
        }

        $data['admin_id'] = $admin->admin_id;
        $newPostId = $this->postModel->create($data);

        if ($newPostId) {
            $this->activityLogModel->create($admin->admin_id, 'created', 'post', $newPostId, ['title' => $data['title']]);
            $this->notificationModel->create($admin->admin_id, 'new_post', "Bài viết mới đã được tạo: '{$data['title']}'");
            return new Response(['message' => 'Thêm bài viết thành công!', 'id' => $newPostId], 201);
        }
        return new Response(['message' => 'Không thể thêm bài viết.'], 500);
    }

    /**
     * Cập nhật một bài viết (PUT /api/admin/posts/{id})
     */
    public function update(Request $request, int $id): Response
    {
        $data = $request->getBody();
        $admin = $request->getUser();

        $post = $this->postModel->findById($id);

        if (!$post) {
            return new Response(['message' => 'Bài viết không tồn tại.'], 404);
        }

        $data['admin_id'] = $admin->admin_id;
        if ($this->postModel->update($id, $data)) {
            $title = $data['title'] ?? $post['title'];
            $this->activityLogModel->create($admin->admin_id, 'updated', 'post', $id, ['title' => $data['title'] ?? $post['title']]);
            $this->notificationModel->create($admin->admin_id, 'post_updated', "Bài viết '{$title}' đã được cập nhật.");
            return new Response(['message' => 'Cập nhật bài viết thành công!']);
        }
        return new Response(['message' => 'Không thể cập nhật bài viết.'], 500);
    }

    /**
     * Xóa mềm một bài viết (DELETE /api/admin/posts/{id})
     */
    public function destroy(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $post = $this->postModel->findById($id);
        if (!$post) {
            return new Response(['message' => 'Bài viết không tồn tại hoặc đã bị xóa.'], 404);
        }

        if ($this->postModel->delete($id)) {
            $this->activityLogModel->create($admin->admin_id, 'soft_deleted', 'post', $id, ['title' => $post['title']]);
            $this->notificationModel->create($admin->admin_id, 'post_soft_deleted', "Bài viết '{$post['title']}' đã được chuyển vào thùng rác.");
            return new Response(['message' => 'Đã chuyển bài viết vào thùng rác.']);
        }
        return new Response(['message' => 'Không thể xóa bài viết.'], 500);
    }

    /**
     * Khôi phục một bài viết (POST /api/admin/posts/{id}/restore)
     */
    public function restore(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        if ($this->postModel->restore($id)) {
            $this->activityLogModel->create($admin->admin_id, 'restored', 'post', $id);
            $this->notificationModel->create($admin->admin_id, 'post_restored', "Một bài viết đã được khôi phục.", "/posts/{$id}");
            return new Response(['message' => 'Khôi phục bài viết thành công!']);
        }
        return new Response(['message' => 'Không thể khôi phục bài viết.'], 500);
    }

    /**
     * Xóa vĩnh viễn một bài viết (DELETE /api/admin/posts/{id}/force)
     */
    public function forceDelete(Request $request, int $id): Response
    {
        $admin = $request->getUser();
        $post = $this->postModel->findById($id);
        if (!$post) {
            return new Response(['message' => 'Bài viết không tồn tại.'], 404);
        }

        if ($this->postModel->forceDelete($id)) {
            $this->activityLogModel->create($admin->admin_id, 'force_deleted', 'post', $id, ['title' => $post['title']]);
            $this->notificationModel->create($admin->admin_id, 'post_force_deleted', "Bài viết '{$post['title']}' đã bị xóa vĩnh viễn.");
            return new Response(['message' => 'Đã xóa vĩnh viễn bài viết!']);
        }
        return new Response(['message' => 'Không thể xóa vĩnh viễn bài viết.'], 500);
    }

    /**
     * Xóa mềm nhiều bài viết (POST /api/admin/posts/bulk-delete)
     */
    public function bulkDelete(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->postModel->bulkDelete($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create($admin->admin_id, 'bulk_soft_deleted', 'post', null, ['count' => $affectedRows, 'ids' => $ids]);
            $this->notificationModel->create($admin->admin_id, 'posts_bulk_deleted', "Đã xóa {$affectedRows} bài viết vào thùng rác.");
            return new Response(['message' => "Đã xóa mềm thành công {$affectedRows} bài viết."]);
        }
        return new Response(['message' => 'Không có bài viết hợp lệ nào được xóa.'], 400);
    }

    /**
     * Khôi phục nhiều bài viết (POST /api/admin/posts/bulk-restore)
     */
    public function bulkRestore(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->postModel->bulkRestore($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create($admin->admin_id, 'bulk_restored', 'post', null, ['count' => $affectedRows, 'ids' => $ids]);
            $this->notificationModel->create($admin->admin_id, 'posts_bulk_restored', "Đã khôi phục {$affectedRows} bài viết.");
            return new Response(['message' => "Đã khôi phục thành công {$affectedRows} bài viết."]);
        }
        return new Response(['message' => 'Không có bài viết nào được khôi phục.'], 400);
    }

    /**
     * Xóa vĩnh viễn nhiều bài viết (POST /api/admin/posts/bulk-force-delete)
     */
    public function bulkForceDelete(Request $request): Response
    {
        $admin = $request->getUser();
        $ids = $request->getBody()['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return new Response(['message' => 'Danh sách ID không hợp lệ.'], 400);
        }

        $affectedRows = $this->postModel->bulkForceDelete($ids);
        if ($affectedRows > 0) {
            $this->activityLogModel->create($admin->admin_id, 'bulk_force_deleted', 'post', null, ['count' => $affectedRows, 'ids' => $ids]);
            $this->notificationModel->create($admin->admin_id, 'posts_bulk_force_deleted', "Đã xóa vĩnh viễn {$affectedRows} bài viết.");
            return new Response(['message' => "Đã xóa vĩnh viễn {$affectedRows} bài viết."]);
        }
        return new Response(['message' => 'Không có bài viết nào được xóa vĩnh viễn.'], 400);
    }
}