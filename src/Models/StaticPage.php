<?php
// src/Models/StaticPage.php

namespace App\Models;

use App\Utils\StringUtil;

class StaticPage extends BaseModel
{
    protected string $table_name = "nk_static_pages";

    /**
     * Lấy tất cả các trang tĩnh (có phân trang và tìm kiếm).
     * @param array $options Tùy chọn lọc và phân trang.
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $searchTerm = $options['search_term'] ?? null;

        $query = "
            SELECT
                sp.*,
                creator.full_name AS creator_name,
                updater.full_name AS updater_name
            FROM
                " . $this->table_name . " AS sp
            LEFT JOIN nk_admins AS creator ON sp.created_by_admin_id = creator.id
            LEFT JOIN nk_admins AS updater ON sp.updated_by_admin_id = updater.id
            WHERE sp.deleted_at IS NULL
        ";

        $params = [];
        $types = "";

        if ($searchTerm) {
            $query .= " AND (sp.title LIKE ? OR sp.slug LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = [$searchTermLike, $searchTermLike];
            $types .= "ss";
        }

        $allowedSortBy = ['id', 'title', 'slug', 'created_at', 'updated_at'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }
        $safeSortBy = "`" . str_replace("`", "``", $sortBy) . "`";
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

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
        $pages = [];
        while ($row = $result->fetch_assoc()) {
            $pages[] = $row;
        }
        $stmt->close();
        return $pages;
    }

    /**
     * Lấy tổng số trang tĩnh.
     * @param array $options Tùy chọn lọc.
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $searchTerm = $options['search_term'] ?? null;
        $query = "SELECT COUNT(id) as total FROM " . $this->table_name . " WHERE deleted_at IS NULL";

        $params = [];
        $types = "";

        if ($searchTerm) {
            $query .= " AND (title LIKE ?)";
            $params[] = "%" . $searchTerm . "%";
            $types .= "s";
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
     * Tìm trang tĩnh bằng ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tìm trang tĩnh bằng slug.
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE slug = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tạo trang tĩnh mới.
     * @param array $data
     * @return int ID của trang mới.
     */
    public function create(array $data): int
    {
        $slug = StringUtil::generateSlug($data['slug'] ?? $data['title']);
        $seoTitle = !empty($data['seo_title']) ? $data['seo_title'] : $data['title'];

        $query = "INSERT INTO " . $this->table_name . " 
                  (title, slug, content, seo_title, meta_description, created_by_admin_id, updated_by_admin_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "sssssii",
            $data['title'],
            $slug,
            $data['content'],
            $seoTitle,
            $data['meta_description'],
            $data['admin_id'],
            $data['admin_id']
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    /**
     * Cập nhật trang tĩnh.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $slug = StringUtil::generateSlug($data['slug'] ?? $data['title']);
        $seoTitle = !empty($data['seo_title']) ? $data['seo_title'] : $data['title'];

        $query = "UPDATE " . $this->table_name . " SET 
                  title = ?, slug = ?, content = ?, seo_title = ?, 
                  meta_description = ?, updated_by_admin_id = ? 
                  WHERE id = ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "sssssii",
            $data['title'],
            $slug,
            $data['content'],
            $seoTitle,
            $data['meta_description'],
            $data['admin_id'],
            $id
        );

        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Xóa mềm trang tĩnh.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Kiểm tra slug đã tồn tại hay chưa.
     * @param string $slug
     * @param int|null $excludeId ID để loại trừ (khi cập nhật).
     * @return bool
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE slug = ? AND deleted_at IS NULL";
        $params = [$slug];
        $types = "s";

        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
            $types .= "i";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count > 0;
    }
}