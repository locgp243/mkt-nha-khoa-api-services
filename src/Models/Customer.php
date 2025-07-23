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
     * Tìm khách hàng bằng email (dùng cho chức năng đăng nhập).
     */
    public function findByEmail(string $email): ?array
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }

    /**
     * Tạo khách hàng mới với mã khách hàng duy nhất được đảm bảo.
     * @param array $data Dữ liệu khách hàng.
     * @return int ID của khách hàng mới, hoặc 0 nếu thất bại.
     */
    public function create(array $data): int
    {
        $maxTries = 5; // Giới hạn số lần thử để tránh vòng lặp vô tận
        $customerCode = '';

        // Vòng lặp để đảm bảo tạo được mã khách hàng duy nhất
        for ($i = 0; $i < $maxTries; $i++) {
            $potentialCode = 'NK' . date('ymd') . strtoupper(substr(uniqid(), 7, 4));

            // Sử dụng hàm findByCustomerCode đã có để kiểm tra
            $existingCustomer = $this->findByCustomerCode($potentialCode);

            if (!$existingCustomer) {
                // Nếu không tìm thấy khách hàng nào có mã này, mã này là duy nhất
                $customerCode = $potentialCode;
                break; // Thoát khỏi vòng lặp
            }
        }

        // Nếu sau $maxTries lần vẫn không tạo được mã duy nhất (xác suất cực kỳ thấp)
        if (empty($customerCode)) {
            // Tạo một mã dự phòng chắc chắn không trùng
            $customerCode = 'NK' . time();
        }

        // --- Phần còn lại của hàm giữ nguyên ---

        // Mã hóa mật khẩu nếu có
        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $query = "INSERT INTO " . $this->table_name . "
                  (customer_code, referring_doctor_1, referring_doctor_2, email, phone, `clinic_name`, address, city, country, password_hash, registered_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param(
            "ssssssssss",
            $customerCode, // Sử dụng mã đã được xác thực là duy nhất
            $data['referring_doctor_1'],
            $data['referring_doctor_2'],
            $data['email'],
            $data['phone'],
            $data['clinic_name'],
            $data['address'],
            $data['city'],
            $data['country'],
            $passwordHash
        );

        if ($stmt->execute()) {
            return $stmt->insert_id;
        }

        // Ghi log lỗi nếu cần thiết
        error_log("Customer creation failed after ensuring unique code: " . $stmt->error);
        return 0;
    }

    /**
     * Cập nhật thông tin khách hàng, bao gồm mật khẩu.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = "";

        $allowedFields = ['referring_doctor_1', 'referring_doctor_2', 'email', 'phone', 'clinic_name', 'address', 'city', 'country'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "`$field` = ?";
                $params[] = $data[$field];
                $types .= "s";
            }
        }

        // Xử lý cập nhật mật khẩu riêng
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "`password_hash` = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $types .= "s";
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

    /**
     * Lấy số lượng đăng ký mới trong một khoảng thời gian.
     * @param string $interval - Ví dụ: '1 MONTH', '1 DAY', '1 YEAR'
     * @return int
     */
    public function getNewRegistrationsCount(string $interval = '1 MONTH'): int
    {
        $query = "SELECT COUNT(id) as count FROM " . $this->table_name . " WHERE registered_at >= NOW() - INTERVAL " . $interval;
        $result = $this->db->query($query);
        return (int) $result->fetch_assoc()['count'];
    }

    /**
     * Lấy danh sách các khách hàng đăng ký gần đây nhất.
     * @param int $limit
     * @return array
     */
    public function getRecentRegistrations(int $limit = 5): array
    {
        $query = "SELECT id, clinic_name, email, registered_at FROM " . $this->table_name . " WHERE deleted_at IS NULL ORDER BY registered_at DESC LIMIT ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $customers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $customers;
    }

    /**
     * Thống kê lượt đăng ký theo từng ngày, tháng, hoặc năm.
     * @param string $period 'day', 'month', 'year'
     * @return array
     */
    public function getRegistrationStatsByPeriod(string $period = 'day', ?int $year = null): array
    {
        $dateSeriesQuery = "";
        $format = '';
        $dateColumnAlias = '';

        // TẠO BẢNG TẠM CHỨA CHUỖI NGÀY/THÁNG
        switch (strtolower($period)) {
            case 'month':
                $format = '%Y-%m';
                $dateColumnAlias = 'month';
                // Tạo chuỗi 12 tháng gần nhất
                $dateSeriesQuery = "
                    SELECT DATE_FORMAT(a.Date, '%Y-%m') AS period
                    FROM (
                        SELECT (CURDATE() - INTERVAL (a.a + (10 * b.a)) MONTH) as Date
                        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                    ) a
                    WHERE a.Date BETWEEN CURDATE() - INTERVAL 11 MONTH AND CURDATE()
                    GROUP BY period ORDER BY period ASC
                ";
                break;

            case 'year':
                $format = '%Y-%m';
                $dateColumnAlias = 'month'; // Vẫn nhóm theo tháng để có 12 cột
                $targetYear = $year ?? (int) date('Y');
                // Tạo chuỗi 12 tháng của năm được chọn
                $dateSeries = [];
                for ($m = 1; $m <= 12; $m++) {
                    $monthStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                    $dateSeries[] = "SELECT '{$targetYear}-{$monthStr}' AS period";
                }
                $dateSeriesQuery = implode(" UNION ALL ", $dateSeries);
                break;

            default: // 'day'
                $format = '%Y-%m-%d';
                $dateColumnAlias = 'day';
                // Tạo chuỗi 7 ngày gần nhất
                $dateSeriesQuery = "
                    SELECT DATE_FORMAT(a.Date, '%Y-%m-%d') AS period
                    FROM (
                        SELECT (CURDATE() - INTERVAL (a.a + (10 * b.a)) DAY) as Date
                        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                    ) a
                    WHERE a.Date BETWEEN CURDATE() - INTERVAL 6 DAY AND CURDATE()
                    ORDER BY a.Date ASC
                ";
                break;
        }

        // TRUY VẤN DỮ LIỆU ĐĂNG KÝ THỰC TẾ
        $subQuery = "
            SELECT 
                DATE_FORMAT(registered_at, ?) as period,
                COUNT(id) as signups
            FROM " . $this->table_name . "
            GROUP BY period
        ";

        // CÂU TRUY VẤN CUỐI CÙNG: KẾT HỢP BẢNG TẠM VÀ DỮ LIỆU THỰC TẾ
        $query = "
            SELECT 
                ds.period AS " . $dateColumnAlias . ",
                COALESCE(rs.signups, 0) as signups
            FROM 
                (" . $dateSeriesQuery . ") AS ds
            LEFT JOIN 
                (" . $subQuery . ") AS rs 
                ON ds.period = rs.period
            ORDER BY 
                ds.period ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $format);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $stats;
    }

    /**
     * Lấy số lượng đăng ký mới của tháng này và so sánh với tháng trước.
     * @return array
     */
    public function getNewRegistrationsCountWithComparison(): array
    {
        // Lấy số lượng đăng ký tháng này (từ đầu tháng đến hiện tại)
        $queryThisMonth = "SELECT COUNT(id) as count FROM " . $this->table_name . " WHERE YEAR(registered_at) = YEAR(CURDATE()) AND MONTH(registered_at) = MONTH(CURDATE())";
        $thisMonthCount = (int) $this->db->query($queryThisMonth)->fetch_assoc()['count'];

        // Lấy số lượng đăng ký của tháng trước
        $queryLastMonth = "SELECT COUNT(id) as count FROM " . $this->table_name . " WHERE YEAR(registered_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(registered_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
        $lastMonthCount = (int) $this->db->query($queryLastMonth)->fetch_assoc()['count'];

        $percentageChange = 0;
        if ($lastMonthCount > 0) {
            $percentageChange = (($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100;
        } elseif ($thisMonthCount > 0) {
            $percentageChange = 100; // Tăng 100% nếu tháng trước không có ai
        }

        return [
            'thisMonthCount' => $thisMonthCount,
            'percentageChange' => round($percentageChange, 1),
            'isGrowth' => $thisMonthCount >= $lastMonthCount
        ];
    }
}