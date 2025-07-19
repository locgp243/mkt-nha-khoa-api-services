<?php
// src/Models/SiteSetting.php

namespace App\Models;

class SiteSetting extends BaseModel
{
    protected string $table_name = "nk_site_settings";

    /**
     * Lấy tất cả cài đặt website.
     * @return array Mảng kết hợp của các cài đặt.
     */
    public function getAllSettings()
    {
        $query = "SELECT setting_key, setting_value FROM " . $this->table_name; //
        $result = $this->db->query($query);
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value']; //
        }
        return $settings;
    }

    /**
     * Cập nhật nhiều cài đặt.
     * @param array $settings Mảng kết hợp key-value của các cài đặt cần cập nhật.
     * @return bool True nếu tất cả thành công.
     */
    public function updateSettings(array $settings)
    {
        $success = true;
        foreach ($settings as $key => $value) {
            // Sử dụng ON DUPLICATE KEY UPDATE để vừa insert vừa update
            $query = "INSERT INTO " . $this->table_name . " (setting_key, setting_value, is_public) VALUES (?, ?, 1)
                      ON DUPLICATE KEY UPDATE setting_value = ?"; //
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("sss", $key, $value, $value);
            if (!$stmt->execute()) {
                $success = false;
                error_log("Failed to update/insert setting {$key}: " . $stmt->error);
            }
            $stmt->close();
        }
        return $success;
    }
}