<?php
// src/Models/BaseModel.php

namespace App\Models;

use mysqli;

/**
 * Lớp Model cơ sở.
 *
 * Chứa kết nối cơ sở dữ liệu và các phương thức chung mà các lớp Model
 * khác có thể kế thừa và sử dụng.
 */
abstract class BaseModel
{
    /**
     * @var mysqli Đối tượng kết nối cơ sở dữ liệu.
     */
    protected mysqli $db;

    /**
     * Hàm khởi tạo nhận vào một đối tượng kết nối mysqli.
     * @param mysqli $db
     */
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }
}