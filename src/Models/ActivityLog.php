<?php
// src/Models/ActivityLog.php

namespace App\Models;

class ActivityLog extends BaseModel
{
    protected string $table_name = "activity_logs";

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
}