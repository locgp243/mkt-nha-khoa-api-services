<?php
namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\Post;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Utils\StringUtil;

/**
 * Quản lý tất cả các nghiệp vụ cho tài nguyên "Post".
 */
class PublicPostController
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
     * Lấy danh sách bài viết (GET /api/public/posts)
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
    public function show(Request $request, string $slug): Response
    {
        $post = $this->postModel->findBySlug($slug);
        if (!$post) {
            return new Response(['message' => 'Bài viết không tồn tại.'], 404);
        }
        return new Response(['data' => $post]);
    }

}