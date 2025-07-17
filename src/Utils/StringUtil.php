<?php
// src/Utils/StringUtil.php

namespace App\Utils;

class StringUtil
{
    /**
     * Chuyển đổi một chuỗi thành dạng slug an toàn cho URL.
     * @param string $title Chuỗi đầu vào.
     * @return string Chuỗi slug.
     */

    public static function generateSlug(string $title): string {
        if ($title === null) return uniqid('post-');
        $slug = mb_strtolower($title, 'UTF-8');
        $patterns = [ '/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/' => 'a', '/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/' => 'e', '/(ì|í|ị|ỉ|ĩ)/' => 'i', '/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/' => 'o', '/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/' => 'u', '/(ỳ|ý|ỵ|ỷ|ỹ)/' => 'y', '/(đ)/' => 'd', '/[^a-z0-9\s-]/u' => '', '/[\s-]+/' => '-', ];
        $slug = preg_replace(array_keys($patterns), array_values($patterns), $slug);
        $slug = trim($slug, '-');
        if (empty($slug)) return uniqid('post-');
        return $slug;
}
}