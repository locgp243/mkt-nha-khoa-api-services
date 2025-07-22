<?php
// src/Models/SiteSetting.php

namespace App\Models;

class SiteSetting extends BaseModel
{
    protected string $table_name = "nk_site_settings";

    /**
<<<<<<< HEAD
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
=======
     * Lấy TẤT CẢ cài đặt cho trang Admin.
     * @return array Mảng kết hợp của các cài đặt.
     */
    public function getAllSettings(): array
    {
        $query = "SELECT setting_key, setting_value, is_public FROM " . $this->table_name;
        $result = $this->db->query($query);
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[] = [
                'key' => $row['setting_key'],
                'value' => $row['setting_value'],
                'is_public' => (bool) $row['is_public']
            ];
>>>>>>> 85a69b00cb8ffbbf76378d0a6eccd5ee43e44613
        }
        return $settings;
    }

    /**
<<<<<<< HEAD
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
=======
     * Chỉ lấy các cài đặt CÔNG KHAI (public) cho trang người dùng.
     * @return array
     */
    public function getPublicSettings(): array
    {
        $query = "SELECT setting_key, setting_value FROM " . $this->table_name . " WHERE is_public = 1";
        $result = $this->db->query($query);
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Cập nhật hàng loạt cài đặt từ trang Admin.
     * @param array $settings Mảng các đối tượng cài đặt, mỗi đối tượng chứa key, value, is_public.
     * @return bool True nếu tất cả thành công.
     */
    public function updateSettings(array $settings): bool
    {
        // Câu lệnh chuẩn bị một lần để tái sử dụng
        $query = "INSERT INTO " . $this->table_name . " (setting_key, setting_value, is_public) 
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_public = VALUES(is_public)";

        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $this->db->error);
            return false;
        }

        // Bắt đầu một transaction để đảm bảo tính toàn vẹn
        $this->db->begin_transaction();

        try {
            foreach ($settings as $setting) {
                // Kiểm tra tính hợp lệ của dữ liệu đầu vào
                if (!isset($setting['key']) || !isset($setting['value']))
                    continue;

                $isPublic = isset($setting['is_public']) ? (int) (bool) $setting['is_public'] : 0;

                $stmt->bind_param("ssi", $setting['key'], $setting['value'], $isPublic);

                if (!$stmt->execute()) {
                    // Nếu một câu lệnh thất bại, rollback và thoát
                    throw new \Exception("Execute failed: " . $stmt->error);
                }
            }
            // Nếu tất cả thành công, commit transaction
            $this->db->commit();
            $stmt->close();
            return true;
        } catch (\Exception $e) {
            // Có lỗi xảy ra, rollback lại
            $this->db->rollback();
            $stmt->close();
            error_log("Setting update transaction failed: " . $e->getMessage());
            return false;
        }
>>>>>>> 85a69b00cb8ffbbf76378d0a6eccd5ee43e44613
    }
}