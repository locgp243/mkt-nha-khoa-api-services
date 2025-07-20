<?php
// src/Models/Notification.php

namespace App\Models;

use Ramsey\Uuid\Uuid;

class Notification extends BaseModel
{
    protected string $table_name = "nk_notifications";

    public function create(int $adminId, string $type, string $message, ?string $link = null): bool
    {
        $uuid = Uuid::uuid4()->toString(); // Tạo UUID v4 chuyên nghiệp

        $query = "INSERT INTO " . $this->table_name . " (id, admin_id, type, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sisss", $uuid, $adminId, $type, $message, $link);

        return $stmt->execute();
    }
}