<?php
namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Utils\StringUtil;

/**
 * Quản lý tất cả các nghiệp vụ CRUD và hành động hàng loạt cho tài nguyên "Category".
 */
class PublicCategoryController
{
    private Category $categoryModel;
    private ActivityLog $activityLogModel;
    private Notification $notificationModel;

    public function __construct(Category $categoryModel)
    {
        $this->categoryModel = $categoryModel;

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
     * Lấy chi tiết một danh mục (GET /api/admin/categories/{slug})
     */
    public function show(Request $request, string $slug): Response
    {
        $category = $this->categoryModel->findBySlug($slug, true);
        if (!$category) {
            return new Response(['message' => 'Danh mục không tồn tại.'], 404);
        }
        return new Response(['data' => $category]);
    }

}