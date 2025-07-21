<?php

namespace App\Models;

use mysqli;
use DateTime; // Thêm use DateTime

class Otp
{
    // Đổi tên biến thành 'db' cho nhất quán với BaseModel của em
    private mysqli $db;

    // Constructor để nhận kết nối MySQLi
    public function __construct(mysqli $dbConnection)
    {
        $this->db = $dbConnection;
    }

    /**
     * Tạo và lưu một mã OTP mới.
     */
    public function create(string $phoneNumber): string|false
    {
        // 1. Xóa OTP cũ (dùng cú pháp MySQLi)
        $stmt_delete = $this->db->prepare("DELETE FROM nk_otp_verifications WHERE phone_number = ?");
        $stmt_delete->bind_param("s", $phoneNumber);
        $stmt_delete->execute();
        $stmt_delete->close();

        // 2. Tạo OTP mới
        $otpCode = strval(rand(100000, 999999));
        // Dùng class DateTime có sẵn của PHP
        $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

        // 3. Chèn OTP mới vào DB (dùng cú pháp MySQLi)
        $stmt_insert = $this->db->prepare(
            "INSERT INTO nk_otp_verifications (phone_number, otp_code, expires_at) VALUES (?, ?, ?)"
        );
        $stmt_insert->bind_param("sss", $phoneNumber, $otpCode, $expiresAt);

        if ($stmt_insert->execute()) {
            $stmt_insert->close();
            return $otpCode;
        }
        $stmt_insert->close();
        return false;
    }

    /**
     * Xác minh mã OTP.
     */
    public function verify(string $phoneNumber, string $otpCode): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM nk_otp_verifications WHERE phone_number = ? AND otp_code = ? AND expires_at > NOW()"
        );
        $stmt->bind_param("ss", $phoneNumber, $otpCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $otpRecord = $result->fetch_assoc();
        $stmt->close();

        if ($otpRecord) {
            // Xóa mã OTP sau khi xác minh thành công
            $stmt_delete = $this->db->prepare("DELETE FROM nk_otp_verifications WHERE phone_number = ?");
            $stmt_delete->bind_param("s", $phoneNumber);
            $stmt_delete->execute();
            $stmt_delete->close();
            return true;
        }
        return false;
    }

    /**
     * Kiểm tra xem SĐT có yêu cầu OTP gần đây không.
     */
    public function hasRecentRequest(string $phoneNumber): bool
    {
        // Sửa lại dùng $this->db và cú pháp MySQLi
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM nk_otp_verifications WHERE phone_number = ? AND created_at > (NOW() - INTERVAL 1 MINUTE)"
        );
        $stmt->bind_param("s", $phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return isset($row['count']) && $row['count'] > 0;
    }
}