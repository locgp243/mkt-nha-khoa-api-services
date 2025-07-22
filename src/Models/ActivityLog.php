<?php
// src/Models/ActivityLog.php

namespace App\Models;

class ActivityLog extends BaseModel
{
    protected string $table_name = "nk_activity_logs";

    public function create(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?array $details = null): int
    {
        $query = "INSERT INTO " . $this->table_name . " (admin_id, action, target_type, target_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($query);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $detailsJson = empty($details) ? "{}" : json_encode($details, JSON_UNESCAPED_UNICODE);

        // Kiểu dữ liệu đúng: int, string, string, int, string, string
        $stmt->bind_param("ississ", $adminId, $action, $targetType, $targetId, $detailsJson, $ipAddress);

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    /**
     * Lấy danh sách nhật ký hoạt động với phân trang và bộ lọc.
     * @param array $options
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 15);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'created_at';
        $sortOrder = $options['sort_order'] ?? 'desc';

        $query = "
            SELECT 
                al.*,
                a.full_name as admin_name,
                a.email as admin_email
            FROM 
                " . $this->table_name . " AS al
            LEFT JOIN nk_admins AS a ON al.admin_id = a.id
        ";

        // Nơi để thêm các điều kiện WHERE nếu bạn muốn lọc theo admin_id, action, v.v.

        $query .= " ORDER BY $sortBy $sortOrder LIMIT ?, ?";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $logs;
    }

    /**
     * Lấy tổng số bản ghi nhật ký.
     * @param array $options
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $query = "SELECT COUNT(id) as total FROM " . $this->table_name;
        // Thêm điều kiện WHERE tương ứng với getAll nếu có
        $result = $this->db->query($query);
        return (int) $result->fetch_assoc()['total'];
    }
}