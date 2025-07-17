<?php
// src/Models/Admin.php

namespace App\Models;

/**
 * Model cho bảng 'admins'.
 * Chịu trách nhiệm tương tác với dữ liệu của quản trị viên.
 */
class Admin extends BaseModel
{
    protected string $table_name = "admins";

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