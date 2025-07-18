<?php
// src/Models/Post.php

namespace App\Models;
use App\Utils\StringUtil;

class Post extends BaseModel
{
    protected string $table_name = "posts";

    /**
     * Lấy danh sách bài viết với đầy đủ tùy chọn lọc, tìm kiếm, sắp xếp.
     * @param array $options - Mảng chứa tất cả tham số.
     * @return array
     */
    public function getAll(array $options = []): array
    {
        // Gán giá trị mặc định
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $status = $options['status'] ?? null;
        $deletedStatus = $options['deleted_status'] ?? 'active';
        $postType = $options['post_type'] ?? null;
        $categoryId = $options['category_id'] ?? null;
        $searchTerm = $options['search_term'] ?? null;
        $categorySlug = $options['category_slug'] ?? null;

        // Câu SELECT với JOIN đến 3 bảng
        $query = "
            SELECT
                p.*,
                c.name AS category_name,
                creator.full_name AS creator_name,
                updater.full_name AS updater_name
            FROM
                " . $this->table_name . " AS p
            LEFT JOIN categories AS c ON p.category_id = c.id
            LEFT JOIN admins AS creator ON p.created_by_admin_id = creator.id
            LEFT JOIN admins AS updater ON p.updated_by_admin_id = updater.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        // Sắp xếp an toàn
        $allowedSortBy = ['id', 'title', 'status', 'post_type', 'created_at', 'updated_at', 'published_at', 'category_name', 'creator_name', 'updater_name', 'is_featured', 'view_count'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }
        $safeSortBy = "`" . str_replace("`", "``", $sortBy) . "`";
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

        // Lọc theo trạng thái
        if ($status) {
            $conditions[] = "p.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        //Lọc theo bài xóa mềm
        if ($deletedStatus === 'active') {
            $conditions[] = "p.deleted_at IS NULL";
        } elseif ($status === 'deleted') {
            $conditions[] = "p.deleted_at IS NOT NULL";
        }

        // Lọc theo loại bài viết
        if ($postType) {
            $conditions[] = "p.post_type = ?";
            $params[] = $postType;
            $types .= "s";
        }
        // Lọc theo ID danh mục
        if ($categoryId) {
            $conditions[] = "p.category_id = ?";
            $params[] = $categoryId;
            $types .= "i";
        }

        if ($categorySlug) {
            $conditions[] = "c.slug = ?";
            $params[] = $categorySlug;
            $types .= "s";
        }

        // Tìm kiếm nâng cao
        if ($searchTerm) {
            $conditions[] = "(p.title LIKE ? OR p.content LIKE ? OR creator.full_name LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = array_merge($params, [$searchTermLike, $searchTermLike, $searchTermLike]);
            $types .= "sss";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY $safeSortBy $sortOrder LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $posts = [];
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
        $stmt->close();
        return $posts;
    }

    /**
     * Lấy tổng số bài viết dựa trên bộ lọc.
     * @param array $options - Mảng chứa các tham số lọc.
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        // Gán giá trị
        $status = $options['status'] ?? null;
        $deletedStatus = $options['deleted_status'] ?? 'active';
        $postType = $options['post_type'] ?? null;
        $categoryId = $options['category_id'] ?? null;
        $searchTerm = $options['search_term'] ?? null;

        $query = "
            SELECT COUNT(p.id) as total
            FROM " . $this->table_name . " AS p
            LEFT JOIN admins AS creator ON p.created_by_admin_id = creator.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        // Lọc theo trạng thái
        if ($status) {
            $conditions[] = "p.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        //Lọc theo bài xóa mềm
        if ($deletedStatus === 'active') {
            $conditions[] = "p.deleted_at IS NULL";
        } elseif ($status === 'deleted') {
            $conditions[] = "p.deleted_at IS NOT NULL";
        }

        // Lọc theo loại bài viết
        if ($postType) {
            $conditions[] = "p.post_type = ?";
            $params[] = $postType;
            $types .= "s";
        }
        // Lọc theo ID danh mục
        if ($categoryId) {
            $conditions[] = "p.category_id = ?";
            $params[] = $categoryId;
            $types .= "i";
        }
        // Tìm kiếm nâng cao
        if ($searchTerm) {
            $conditions[] = "(p.title LIKE ? OR p.content LIKE ? OR creator.full_name LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = array_merge($params, [$searchTermLike, $searchTermLike, $searchTermLike]);
            $types .= "sss";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }
    /**
     * Tìm một bài viết theo ID, có JOIN để lấy tên danh mục và tác giả.
     * @param int $id ID của bài viết.
     * @return array|null Dữ liệu bài viết hoặc null nếu không tìm thấy.
     */
    public function findById(int $id): ?array
    {
        $query = "
            SELECT
                p.*,
                c.name AS category_name,
                creator.full_name AS creator_name
            FROM
                " . $this->table_name . " AS p
            LEFT JOIN categories AS c ON p.category_id = c.id
            LEFT JOIN admins AS creator ON p.created_by_admin_id = creator.id
            WHERE p.id = ? AND p.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();
        return $post;
    }

    /**
     * Tạo bài viết mới.
     * @param array $data Dữ liệu bài viết.
     * @return int ID của bài viết mới, hoặc 0 nếu thất bại.
     */
    public function create(array $data): int
    {
        // Tự động tạo slug nếu không được cung cấp
        $slugSource = !empty($data['slug']) ? $data['slug'] : $data['title'];
        $slug = StringUtil::generateSlug($slugSource);

        $query = "INSERT INTO " . $this->table_name . " 
                    (title, slug, content, excerpt, status, post_type, category_id, 
                     featured_image_url, created_by_admin_id, updated_by_admin_id, published_at,
                     is_featured, view_count, seo_title, meta_description) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);

        // Nếu status là 'published', thì gán ngày published_at là hiện tại
        $publishedAt = ($data['status'] === 'published') ? date('Y-m-d H:i:s') : null;
        $seoTitle = !empty($data['seo_title']) ? $data['seo_title'] : $data['title'];
        $viewCount = 0;

        $stmt->bind_param(
            "ssssssisissiiss",
            $data['title'],
            $slug,
            $data['content'],
            $data['excerpt'],
            $data['status'],
            $data['post_type'],
            $data['category_id'],
            $data['featured_image_url'],
            $data['admin_id'],
            $data['admin_id'],
            $publishedAt,
            $data['is_featured'],
            $viewCount,
            $seoTitle,
            $data['meta_description'],
        );

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        }
        error_log("Post creation failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }

    /**
     * Cập nhật bài viết một cách linh hoạt.
     * @param int $id ID của bài viết cần cập nhật.
     * @param array $data Dữ liệu cập nhật.
     * @return bool True nếu thành công.
     */
    public function update(int $id, array $data): bool
    {
        $currentPost = $this->findById($id);
        if (!$currentPost) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET ";
        $fields = [];
        $params = [];
        $types = "";

        // Thêm các trường vào câu lệnh nếu chúng tồn tại trong $data
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = $data['title'];
            $types .= "s";
            $slugSource = !empty($data['slug']) ? $data['slug'] : $data['title'];
            $fields[] = "slug = ?";
            $params[] = StringUtil::generateSlug($slugSource);
            $types .= "s";
        }
        if (isset($data['content'])) {
            $fields[] = "content = ?";
            $params[] = $data['content'];
            $types .= "s";
        }
        if (isset($data['excerpt'])) {
            $fields[] = "excerpt = ?";
            $params[] = $data['excerpt'];
            $types .= "s";
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
            $types .= "s";
            if ($data['status'] === 'published' && $currentPost['status'] !== 'published') {
                $fields[] = "published_at = ?";
                $params[] = date('Y-m-d H:i:s');
                $types .= "s";
            }
        }
        if (isset($data['post_type'])) {
            $fields[] = "post_type = ?";
            $params[] = $data['post_type'];
            $types .= "s";
        }
        if (isset($data['category_id'])) {
            $fields[] = "category_id = ?";
            $params[] = (int) $data['category_id'];
            $types .= "i";
        }
        if (isset($data['featured_image_url'])) {
            $fields[] = "featured_image_url = ?";
            $params[] = $data['featured_image_url'];
            $types .= "s";
        }

        // ================== BỔ SUNG CÁC TRƯỜNG CÒN THIẾU ==================
        if (isset($data['is_featured'])) {
            $fields[] = "is_featured = ?";
            $params[] = (int) $data['is_featured']; // Ép kiểu về 0 hoặc 1
            $types .= "i";
        }
        if (isset($data['view_count'])) {
            $fields[] = "view_count = ?";
            $params[] = (int) $data['view_count'];
            $types .= "i";
        }
        if (isset($data['estimated_read_time'])) {
            $fields[] = "estimated_read_time = ?";
            $params[] = (int) $data['estimated_read_time'];
            $types .= "i";
        }
        if (isset($data['seo_title'])) {
            // Nếu seo_title rỗng, lấy giá trị từ title mới (nếu có) hoặc title cũ
            $seoTitleValue = !empty($data['seo_title'])
                ? $data['seo_title']
                : ($data['title'] ?? $currentPost['title']);

            $fields[] = "seo_title = ?";
            $params[] = $seoTitleValue;
            $types .= "s";
        }
        if (isset($data['meta_description'])) {
            $fields[] = "meta_description = ?";
            $params[] = $data['meta_description'];
            $types .= "s";
        }
        // ====================================================================

        // Luôn cập nhật người sửa và thời gian sửa
        if (isset($data['admin_id'])) {
            $fields[] = "updated_by_admin_id = ?";
            $params[] = (int) $data['admin_id'];
            $types .= "i";
        }
        $fields[] = "updated_at = CURRENT_TIMESTAMP";

        if (count($fields) <= 1) { // Nếu chỉ có updated_at thì không chạy
            return true;
        }

        $query .= implode(', ', $fields);
        $query .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);

        $success = $stmt->execute();
        if (!$success) {
            error_log("Post update failed: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    /**
     * Xóa mềm một bài viết (soft delete).
     * @param int $id ID của bài viết.
     * @return bool True nếu thành công.
     */
    public function delete(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Kiểm tra xem slug đã tồn tại chưa, ngoại trừ một ID bài viết nhất định.
     * @param string $slug Slug cần kiểm tra.
     * @param int|null $excludeId ID bài viết cần loại trừ (khi sửa).
     * @return bool True nếu slug đã tồn tại.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE slug = ? AND deleted_at IS NULL";
        if ($excludeId !== null) {
            $query .= " AND id != ?";
        }
        $stmt = $this->db->prepare($query);
        if ($excludeId !== null) {
            $stmt->bind_param("si", $slug, $excludeId);
        } else {
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count > 0;
    }

    /**
     * Xóa mềm nhiều bài viết theo danh sách ID (soft delete).
     * @param array $ids Mảng các ID bài viết cần xóa.
     * @return int Số lượng bản ghi đã được cập nhật.
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders) AND deleted_at IS NULL";

        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    /**
     * Khôi phục một bài viết đã xóa mềm.
     * @param int $id ID của bài viết cần khôi phục.
     * @return bool True nếu thành công.
     */
    public function restore(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NULL WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Khôi phục nhiều bài viết đã xóa mềm.
     * @param array $ids Mảng các ID cần khôi phục.
     * @return int Số lượng bản ghi đã được cập nhật.
     */
    public function bulkRestore(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NULL WHERE id IN ($placeholders) AND deleted_at IS NOT NULL";

        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    /**
     * Xóa vĩnh viễn một bài viết khỏi cơ sở dữ liệu.
     * @param int $id ID của bài viết cần xóa.
     * @return bool True nếu thành công.
     */
    public function forceDelete(int $id): bool
    {
        // Thận trọng: Hành động này không thể hoàn tác.
        // Cân nhắc xóa các bản ghi liên quan (ví dụ: comments) trước khi xóa bài viết.
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Xóa vĩnh viễn nhiều bài viết.
     * @param array $ids Mảng các ID cần xóa.
     * @return int Số lượng bản ghi đã bị xóa.
     */
    public function bulkForceDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "DELETE FROM " . $this->table_name . " WHERE id IN ($placeholders)";

        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    /**
     * Tìm một bài viết theo slug, chỉ lấy bài đã published và chưa bị xóa.
     * @param string $slug Slug của bài viết.
     * @return array|null Dữ liệu bài viết hoặc null nếu không tìm thấy.
     */
    public function findBySlug(string $slug): ?array
    {
        $query = "
            SELECT
                p.*,
                c.name AS category_name,
                creator.full_name AS creator_name
            FROM
                " . $this->table_name . " AS p
            LEFT JOIN categories AS c ON p.category_id = c.id
            LEFT JOIN admins AS creator ON p.created_by_admin_id = creator.id
            WHERE p.slug = ? AND p.status = 'published' AND p.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $post = $result->fetch_assoc();
        $stmt->close();

        // Tăng lượt xem mỗi khi có người đọc
        if ($post) {
            $this->incrementViewCount($post['id']);
        }

        return $post;
    }

    private function incrementViewCount(int $id): void
    {
        $query = "UPDATE " . $this->table_name . " SET view_count = view_count + 1 WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

}