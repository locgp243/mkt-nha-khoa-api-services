<?php
// src/Models/Admin.php

namespace App\Models;

/**
 * Model cho bảng 'admins'.
 * Chịu trách nhiệm tương tác với dữ liệu của quản trị viên.
 */
class Admin extends BaseModel
{
    protected string $table_name = "nk_admins";

    /**
     * Lấy danh sách người dùng với các tùy chọn.
     * @param array $options Tùy chọn lọc, sắp xếp, phân trang.
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'desc';

        $query = "SELECT id, full_name, email, role, status, last_login_at, created_at FROM " . $this->table_name . " WHERE deleted_at IS NULL";

        // Sắp xếp an toàn
        $allowedSortBy = ['id', 'full_name', 'email', 'role', 'status', 'last_login_at', 'created_at'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }
        $query .= " ORDER BY " . $sortBy . " " . ($sortOrder === 'asc' ? 'ASC' : 'DESC');
        $query .= " LIMIT ?, ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
        return $users;
    }

    /**
     * Lấy tổng số người dùng.
     */
    public function getTotalCount(array $options = []): int
    {
        $query = "SELECT COUNT(id) as total FROM " . $this->table_name . " WHERE deleted_at IS NULL";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    /**
     * Tìm một admin dựa trên ID.
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT id, full_name, email, password_hash, role, status FROM " . $this->table_name . " WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();
        return $admin;
    }

    /**
     * Tìm một admin dựa trên địa chỉ email.
     *
     * @param string $email Email cần tìm.
     * @return array|null Dữ liệu của admin nếu tìm thấy, ngược lại trả về null.
     */
    public function findByEmail(string $email): ?array
    {
        $query = "SELECT id, full_name, email, password_hash, role, status FROM " . $this->table_name . " WHERE email = ? LIMIT 1";

        // Chuẩn bị câu lệnh
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            // Ghi lỗi nếu câu lệnh có vấn đề
            error_log('MySQL prepare() failed: ' . $this->db->error);
            return null;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        $stmt->close();
        return $admin;
    }

    /**
     * Tạo người dùng mới.
     * @param array $data Dữ liệu người dùng.
     * @return int ID của người dùng mới, hoặc 0 nếu thất bại.
     */
    public function create(array $data): int
    {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $query = "INSERT INTO " . $this->table_name . " (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        $status = $data['status'] ?? 'active';
        $stmt->bind_param("sssss", $data['full_name'], $data['email'], $passwordHash, $data['role'], $status);

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    /**
     * Cập nhật thông tin người dùng.
     * @param int $id ID người dùng.
     * @param array $data Dữ liệu cần cập nhật.
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = "";

        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
            $types .= "s";
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
            $types .= "s";
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
            $types .= "s";
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
            $types .= "s";
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $types .= "s";
        }

        if (empty($fields)) {
            return true; // Không có gì để cập nhật
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Xóa mềm người dùng.
     */
    public function delete(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Kiểm tra email đã tồn tại hay chưa.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];
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


    /**
     * Cập nhật thời gian và địa chỉ IP đăng nhập cuối cùng của admin.
     *
     * @param int $adminId ID của admin.
     * @param string $lastLoginIp Địa chỉ IP đăng nhập.
     * @return bool True nếu cập nhật thành công, false nếu thất bại.
     */
    public function updateLastLogin(int $adminId, string $lastLoginIp): bool
    {
        $query = "UPDATE " . $this->table_name . " SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?";

        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            error_log('MySQL prepare() failed: ' . $this->db->error);
            return false;
        }

        $stmt->bind_param("si", $lastLoginIp, $adminId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}