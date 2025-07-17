<?php
// src/Models/Customer.php

namespace App\Models;

class Customer extends BaseModel
{
    protected string $table_name = "customers";

    /**
     * Lấy tất cả khách hàng.
     * @return array Mảng các khách hàng.
     */
    public function getAll()
    {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY registered_at DESC"; //
        $result = $this->db->query($query);
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        return $customers;
    }

    /**
     * Tạo khách hàng mới.
     * @param array $data Dữ liệu khách hàng.
     * @return bool True nếu thành công.
     */
    public function create(array $data)
    {
        $query = "INSERT INTO " . $this->table_name . " (customer_code, name, email, phone, status, plan, registered_at, trial_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "ssssssss",
            $data['customer_code'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['status'],
            $data['plan'],
            $data['registered_at'],
            $data['trial_end_date']
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Cập nhật trạng thái khách hàng.
     * @param int $id ID khách hàng.
     * @param string $status Trạng thái mới.
     * @return bool True nếu thành công.
     */
    public function updateStatus(int $id, string $status)
    {
        $query = "UPDATE " . $this->table_name . " SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"; //
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("si", $status, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}