<?php
// src/Models/Contact.php

namespace App\Models;

class Contact extends BaseModel
{
    protected string $table_name = "nk_contacts";

    /**
     * Lấy danh sách các liên hệ với phân trang, lọc và tìm kiếm.
     * @param array $options
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'created_at';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $status = $options['status'] ?? null;
        $searchTerm = $options['search_term'] ?? null;

        $query = "
            SELECT 
                c.*,
                a.full_name as replied_by_admin_name
            FROM 
                " . $this->table_name . " AS c
            LEFT JOIN nk_admins as a ON c.replied_by_admin_id = a.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        if ($status) {
            $conditions[] = "c.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($searchTerm) {
            $conditions[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.subject LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = array_merge($params, [$searchTermLike, $searchTermLike, $searchTermLike]);
            $types .= "sss";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $allowedSortBy = ['id', 'full_name', 'email', 'status', 'created_at', 'replied_at'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'created_at';
        }

        $query .= " ORDER BY $sortBy $sortOrder LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $contacts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $contacts;
    }

    /**
     * Lấy tổng số liên hệ dựa trên bộ lọc.
     * @param array $options
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $status = $options['status'] ?? null;
        $searchTerm = $options['search_term'] ?? null;

        $query = "SELECT COUNT(id) as total FROM " . $this->table_name;

        $conditions = [];
        $params = [];
        $types = "";

        if ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($searchTerm) {
            $conditions[] = "(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)";
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
     * Tìm liên hệ theo ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tạo một liên hệ mới từ form public.
     * @param array $data
     * @return int ID của liên hệ mới
     */
    public function create(array $data): int
    {
        $query = "INSERT INTO " . $this->table_name . " (full_name, email, phone, subject, message, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param(
            "ssssss",
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['subject'],
            $data['message'],
            $ipAddress
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    /**
     * Cập nhật trạng thái và ghi chú cho một liên hệ.
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $query = "UPDATE " . $this->table_name . " SET status = ?, notes = ?, replied_by_admin_id = ?, replied_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "ssii",
            $data['status'],
            $data['notes'],
            $data['admin_id'],
            $id
        );
        return $stmt->execute();
    }

    /**
     * Xóa một liên hệ.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}