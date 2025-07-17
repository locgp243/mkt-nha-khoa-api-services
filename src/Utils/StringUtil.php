<?php
// src/Utils/StringUtil.php

namespace App\Utils;

class StringUtil
{
    /**
     * Chuyển đổi một chuỗi thành dạng slug an toàn cho URL.
     * @param string $string Chuỗi đầu vào.
     * @return string Chuỗi slug.
     */
    public static function createSlug(string $string): string
    {
        // Chuyển chuỗi về chữ thường và loại bỏ các ký tự đặc biệt
        $slug = mb_strtolower($string, 'UTF-8');
        $slug = preg_replace('/[áàảạãăắằẳặẵâấầẩậẫ]/u', 'a', $slug);
        $slug = preg_replace('/[éèẻẹẽêếềểệễ]/u', 'e', $slug);
        $slug = preg_replace('/[íìỉịĩ]/u', 'i', $slug);
        $slug = preg_replace('/[óòỏọõôốồổộỗơớờởợỡ]/u', 'o', $slug);
        $slug = preg_replace('/[úùủụũưứừửựữ]/u', 'u', $slug);
        $slug = preg_replace('/[ýỳỷỵỹ]/u', 'y', $slug);
        $slug = preg_replace('/[đ]/u', 'd', $slug);

        // Loại bỏ các ký tự không phải là chữ cái, số hoặc dấu gạch ngang
        $slug = preg_replace('/[^a-z0-9-]+/u', '', $slug);

        // Thay thế khoảng trắng hoặc nhiều dấu gạch ngang bằng một dấu gạch ngang duy nhất
        $slug = preg_replace('/[\s_-]+/', '-', $slug);

        // Loại bỏ dấu gạch ngang ở đầu và cuối chuỗi
        $slug = trim($slug, '-');

        return $slug;
    }
}