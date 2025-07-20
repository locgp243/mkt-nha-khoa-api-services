<?php
// src/Models/PricingPackage.php

namespace App\Models;

class PricingPackage extends BaseModel
{
    protected string $table_name = "nk_pricing_packages";

    /**
     * Lấy danh sách các gói giá với phân trang, lọc, tìm kiếm và sắp xếp.
     * @param array $options - Mảng chứa các tham số.
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 100000000);
        $page = (int) ($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'asc';
        $isActive = $options['is_active'] ?? null;

        $query = "
            SELECT
                pp.*,
                creator.full_name AS creator_name,
                updater.full_name AS updater_name
            FROM
                " . $this->table_name . " AS pp
            LEFT JOIN nk_admins AS creator ON pp.created_by_admin_id = creator.id
            LEFT JOIN nk_admins AS updater ON pp.updated_by_admin_id = updater.id
        ";

        $conditions = [];
        $params = [];
        $types = "";

        $allowedSortBy = ['id', 'name', 'price_monthly', 'is_featured', 'is_active', 'created_at', 'updated_at'];
        if (!in_array(strtolower($sortBy), $allowedSortBy)) {
            $sortBy = 'id';
        }
        $safeSortBy = "`" . str_replace("`", "``", $sortBy) . "`";
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

        if ($isActive !== null) {
            $conditions[] = "pp.is_active = ?";
            $params[] = (int) $isActive;
            $types .= "i";
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY $safeSortBy $sortOrder LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";

        $stmt = $this->db->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $row['features'] = json_decode($row['features'], true);
            $packages[] = $row;
        }
        $stmt->close();
        return $packages;
    }

    /**
     * Lấy tổng số gói giá dựa trên các bộ lọc.
     * @param array $options - Mảng chứa các tham số lọc.
     * @return int
     */
    public function getTotalCount(array $options = []): int
    {
        $isActive = $options['is_active'] ?? null;

        $query = "SELECT COUNT(id) as total FROM " . $this->table_name;

        $conditions = [];
        $params = [];
        $types = "";

        if ($isActive !== null) {
            $conditions[] = "is_active = ?";
            $params[] = (int) $isActive;
            $types .= "i";
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
     * Tìm gói giá theo ID.
     * @param int $id ID của gói giá.
     * @return array|null Dữ liệu gói giá hoặc null nếu không tìm thấy.
     */
    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
        if ($package) {
            $package['features'] = json_decode($package['features'], true);
        }
        $stmt->close();
        return $package;
    }

    /**
     * Tạo gói giá mới.
     * @param array $data Dữ liệu gói giá.
     * @return int ID của gói giá mới được tạo, hoặc 0 nếu thất bại.
     */
    public function create(array $data): int
    {
        $query = "INSERT INTO " . $this->table_name . " (name, description, price_monthly, features, is_featured, is_active, created_by_admin_id, updated_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $featuresJson = json_encode($data['features'], JSON_UNESCAPED_UNICODE);
        $priceMonthly = empty($data['price_monthly']) ? 0 : $data['price_monthly'];
        $stmt->bind_param(
            "ssdsiiii",
            $data['name'],
            $data['description'],
            $priceMonthly,
            $featuresJson,
            $data['is_featured'],
            $data['is_active'],
            $data['admin_id'],
            $data['admin_id']
        );
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        return 0;
    }

    /**
     * Cập nhật gói giá.
     * @param int $id ID của gói giá.
     * @param array $data Dữ liệu cập nhật.
     * @return bool True nếu thành công.
     */
    public function update(int $id, array $data): bool
    {
        $priceMonthly = empty($data['price_monthly']) ? 0 : $data['price_monthly'];
        $featuresJson = json_encode($data['features'], JSON_UNESCAPED_UNICODE);
        $query = "UPDATE " . $this->table_name . " SET name = ?, description = ?, price_monthly = ?, features = ?, is_featured = ?, is_active = ?, updated_by_admin_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "ssdsiiii",
            $data['name'],
            $data['description'],
            $priceMonthly,
            $featuresJson,
            $data['is_featured'],
            $data['is_active'],
            $data['admin_id'],
            $id
        );
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Xóa một gói giá.
     * @param int $id ID của gói giá.
     * @return bool True nếu thành công.
     */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}