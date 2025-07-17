<?php
// src/Models/Category.php

namespace App\Models;
use App\Utils\StringUtil;

class Category extends BaseModel
{
    protected string $table_name = "categories";

    /**
     * Lấy danh mục với phân trang, lọc, tìm kiếm và sắp xếp nâng cao.
     * @param array $options - Mảng chứa tất cả các tham số.
     * @return array
     */
    public function getAll(array $options = []): array
    {
        // Gán giá trị mặc định
        $type = $options['type'] ?? null;
        $searchTerm = $options['search_term'] ?? null;
        $status = $options['status'] ?? 'active';
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'asc';

        $query = "
            SELECT
                c.*,
                creator.full_name AS creator_name,
                updater.full_name AS updater_name
            FROM
                " . $this->table_name . " AS c
            LEFT JOIN admins AS creator ON c.created_by_admin_id = creator.id
            LEFT JOIN admins AS updater ON c.updated_by_admin_id = updater.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        // Sắp xếp an toàn
        $allowedSortBy = ['id', 'name', 'slug', 'category_type', 'created_at', 'updated_at', 'creator_name', 'updater_name'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }
        $safeSortBy = "`" . str_replace("`", "``", $sortBy) . "`";
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

        // --- SỬA LỖI Ở ĐÂY: Thêm alias "c." vào các cột của bảng categories ---
        if ($type) {
            $conditions[] = "c.category_type = ?";
            $params[] = $type;
            $types .= "s";
        }
        if ($searchTerm) {
            $conditions[] = "(c.name LIKE ? OR c.slug LIKE ? OR creator.full_name LIKE ? OR updater.full_name LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = array_merge($params, [$searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike]);
            $types .= "ssss";
        }
        if ($status === 'active') {
            $conditions[] = "c.deleted_at IS NULL"; // <--- SỬA LỖI
        } elseif ($status === 'deleted') {
            $conditions[] = "c.deleted_at IS NOT NULL"; // <--- SỬA LỖI
        }
        // --- KẾT THÚC SỬA LỖI ---

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY $safeSortBy $sortOrder LIMIT ?, ?";

        $stmt = $this->db->prepare($query);

        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $stmt->close();
        return $categories;
    }

    /**
     * Lấy tổng số danh mục dựa trên các bộ lọc (đã cập nhật).
     * @param array $options - Mảng chứa các tham số lọc.
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $type = $options['type'] ?? null;
        $searchTerm = $options['search_term'] ?? null;
        $status = $options['status'] ?? 'active';

        $query = "
            SELECT COUNT(c.id) as total
            FROM " . $this->table_name . " AS c
            LEFT JOIN admins AS creator ON c.created_by_admin_id = creator.id
            LEFT JOIN admins AS updater ON c.updated_by_admin_id = updater.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        // --- SỬA LỖI Ở ĐÂY: Thêm alias "c." vào các cột của bảng categories ---
        if ($type) {
            $conditions[] = "c.category_type = ?";
            $params[] = $type;
            $types .= "s";
        }
        if ($searchTerm) {
            $conditions[] = "(c.name LIKE ? OR c.slug LIKE ? OR creator.full_name LIKE ? OR updater.full_name LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = array_merge($params, [$searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike]);
            $types .= "ssss";
        }
        if ($status === 'active') {
            $conditions[] = "c.deleted_at IS NULL"; // <--- SỬA LỖI
        } elseif ($status === 'deleted') {
            $conditions[] = "c.deleted_at IS NOT NULL"; // <--- SỬA LỖI
        }
        // --- KẾT THÚC SỬA LỖI ---

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
     * Tìm danh mục theo ID.
     * Mặc định chỉ tìm các bản ghi CHƯA bị xóa mềm.
     * @param int $id ID của danh mục.
     * @param bool $includeDeleted Có bao gồm bản ghi đã xóa mềm hay không.
     * @return array|null Dữ liệu danh mục hoặc null nếu không tìm thấy.
     */
    public function findBySlug(string $slug): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE slug = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $stmt->close();
        return $category;
    }


    public function findById(int $id, bool $includeDeleted = false): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?"; //
        if (!$includeDeleted) {
            $query .= " AND deleted_at IS NULL"; //
        }
        $query .= " LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $stmt->close();
        return $category;
    }

    /**
     * Tạo danh mục mới.
     * @param array $data Dữ liệu danh mục (name, slug, description, category_type, created_by_admin_id).
     * @return int ID của danh mục mới được tạo, hoặc 0 nếu thất bại.
     */
    public function create(array $data): int
    {
        // Tự động tạo slug nếu không được cung cấp
        $slugSource = !empty($data['slug']) ? $data['slug'] : $data['name'];
        $slug = StringUtil::generateSlug($slugSource);

        $query = "INSERT INTO " . $this->table_name . " (name, slug, description, category_type, created_by_admin_id, updated_by_admin_id) VALUES (?, ?, ?, ?, ?, ?)"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "ssssii",
            $data['name'], //
            $slug, //
            $data['description'], //
            $data['category_type'], //
            $data['admin_id'],
            $data['admin_id']
        );
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            return $newId;
        }
        error_log("Category creation failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }

    /**
     * Cập nhật danh mục.
     * @param int $id ID của danh mục.
     * @param array $data Dữ liệu cập nhật (name, slug, description, updated_by_admin_id).
     * @return bool True nếu thành công.
     */
    public function update(int $id, array $data): bool
    {
        $name = $data['name'] ?? '';
        $userInputSlug = $data['slug'] ?? null;
        // 2. Quyết định nguồn tạo slug: nếu slug người dùng nhập vào không rỗng, dùng nó. Ngược lại, dùng name.
        //    Hàm !empty() sẽ coi chuỗi rỗng '' là "empty", xử lý đúng ý đồ của người dùng.
        $slugSource = !empty($userInputSlug) ? $userInputSlug : $name;
        // 3. Tạo slug sạch từ nguồn đã quyết định.
        $slug = StringUtil::generateSlug($slugSource);


        $query = "UPDATE " . $this->table_name . " SET name = ?, slug = ?, description = ?, updated_by_admin_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "sssii",
            $data['name'],
            $slug,
            $data['description'],
            $data['admin_id'],
            $id
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Xóa mềm một danh mục theo ID (soft delete).
     * @param int $id ID của danh mục.
     * @return bool True nếu thành công.
     */
    public function delete(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Xóa mềm nhiều danh mục theo danh sách ID (soft delete).
     * @param array $ids Mảng các ID danh mục cần xóa.
     * @return int Số lượng bản ghi đã được đánh dấu xóa thành công.
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders) AND deleted_at IS NULL"; // Thêm điều kiện để không update lại
        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows; // <-- SỬA LỖI: Lấy số lượng hàng bị ảnh hưởng
        $stmt->close();
        return $affectedRows; // <-- SỬA LỖI: Trả về số lượng hàng
    }

    /**
     * KHÔI PHỤC danh mục đã xóa mềm.
     * @param int $id ID của danh mục cần khôi phục.
     * @return bool True nếu thành công.
     */
    public function restore(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NULL WHERE id = ?"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * XÓA CỨNG (vĩnh viễn) một danh mục.
     * @param int $id ID của danh mục cần xóa vĩnh viễn.
     * @return bool True nếu thành công.
     */
    public function forceDelete(int $id): bool
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * KHÔI PHỤC nhiều danh mục đã xóa mềm.
     * @param array $ids Mảng các ID danh mục cần khôi phục.
     * @return int Số lượng bản ghi đã được khôi phục thành công.
     */
    public function bulkRestore(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NULL WHERE id IN ($placeholders) AND deleted_at IS NOT NULL"; // Thêm điều kiện để không update lại
        $stmt = $this->db->prepare($query);
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows; // <-- SỬA LỖI: Lấy số lượng hàng bị ảnh hưởng
        $stmt->close();
        return $affectedRows; // <-- SỬA LỖI: Trả về số lượng hàng
    }

    /**
     * XÓA CỨNG (vĩnh viễn) nhiều danh mục.
     * @param array $ids Mảng các ID danh mục cần xóa vĩnh viễn.
     * @return int Số lượng bản ghi đã được xóa thành công.
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
        $affectedRows = $stmt->affected_rows; // <-- SỬA LỖI: Lấy số lượng hàng bị ảnh hưởng
        $stmt->close();
        return $affectedRows; // <-- SỬA LỖI: Trả về số lượng hàng
    }

    /**
     * Kiểm tra xem slug đã tồn tại chưa, ngoại trừ một ID nhất định.
     * Mặc định chỉ kiểm tra các slug của bản ghi CHƯA bị xóa mềm.
     * @param string $slug Slug cần kiểm tra.
     * @param int|null $excludeId ID danh mục cần loại trừ khi kiểm tra (khi sửa).
     * @return bool True nếu slug đã tồn tại, false nếu không.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE slug = ? AND deleted_at IS NULL"; //
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
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count > 0;
    }
}