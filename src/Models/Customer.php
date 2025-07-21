<?php
// src/Models/Customer.php

namespace App\Models;

class Customer extends BaseModel
{
    protected string $table_name = "nk_customers";

    /**
     * Lấy danh sách khách hàng với đầy đủ tùy chọn.
     * @param array $options
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 10);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $searchTerm = $options['search_term'] ?? null;

        $query = "SELECT * FROM " . $this->table_name . " WHERE deleted_at IS NULL";

        $params = [];
        $types = "";

        if ($searchTerm) {
            $query .= " AND (referring_doctor_1 LIKE ? OR email LIKE ? OR phone LIKE ? OR `clinic_name` LIKE ? OR customer_code LIKE ?)";
            $searchTermLike = "%" . $searchTerm . "%";
            $params = [$searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike];
            $types .= "sssss";
        }

        $allowedSortBy = ['id', 'referring_doctor_1', 'email', 'clinic_name', 'registered_at', 'customer_code'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }

        $query .= " ORDER BY $sortBy " . ($sortOrder === 'asc' ? 'ASC' : 'DESC') . " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $stmt->close();
        return $customers;
    }

    /**
     * Lấy tổng số khách hàng dựa trên bộ lọc.
     * @param array $options
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $searchTerm = $options['search_term'] ?? null;
        $query = "SELECT COUNT(id) as total FROM " . $this->table_name . " WHERE deleted_at IS NULL";

        $params = [];
        $types = "";

        if ($searchTerm) {
            $query .= " AND (referring_doctor_1 LIKE ? OR email LIKE ? OR phone LIKE ? OR `clinic_name` LIKE ? OR customer_code LIKE ?)";
            $params = array_fill(0, 5, "%" . $searchTerm . "%");
            $types .= "sssss";
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
     * Tìm khách hàng theo ID.
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tìm khách hàng theo mã khách hàng (customer_code).
     * @param string $customerCode
     * @return array|null
     */
    public function findByCustomerCode(string $customerCode): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE customer_code = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $customerCode);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tạo khách hàng mới.
     * @param array $data
     * @return int ID của khách hàng mới.
     */
    public function create(array $data): int
    {
        // Tự động tạo customer_code duy nhất, dễ đọc
        $customerCode = 'NK' . date('ymd') . strtoupper(substr(uniqid(), 7, 4));

        $query = "INSERT INTO " . $this->table_name . " 
                  (customer_code, referring_doctor_1, referring_doctor_2, email, phone, `clinic_name`, address, city, country, registered_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "sssssssss",
            $customerCode,
            $data['referring_doctor_1'],
            $data['referring_doctor_2'],
            $data['email'],
            $data['phone'],
            $data['clinic_name'],
            $data['address'],
            $data['city'],
            $data['country']
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = "";

        $allowedFields = ['referring_doctor_1', 'referring_doctor_2', 'email', 'phone', 'clinic_name', 'address', 'city', 'country'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                // Sử dụng backtick cho tên cột clinic name có khoảng trắng
                $columnName = ($field === 'clinic_name') ? '`clinic_name`' : $field;
                $fields[] = "$columnName = ?";
                $params[] = $data[$field];
                $types .= "s";
            }
        }

        if (empty($fields)) {
            return true;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

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
}