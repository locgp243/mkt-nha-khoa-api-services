<?php
// src/Models/PricingPackage.php

namespace App\Models;

class PricingPackage extends BaseModel
{
    protected string $table_name = "pricing_packages";

    /**
     * Lấy tất cả các gói giá đang hoạt động và có thể hiển thị công khai.
     * @return array Mảng các gói giá.
     */
    public function getPublicPackages()
    {
        $query = "SELECT id, name, description, price_monthly, features, is_featured FROM " . $this->table_name . " WHERE is_active = 1 ORDER BY price_monthly ASC"; //
        $result = $this->db->query($query);
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $row['features'] = json_decode($row['features'], true); // Giải mã JSON cho 'features'
            $packages[] = $row;
        }
        return $packages;
    }
}