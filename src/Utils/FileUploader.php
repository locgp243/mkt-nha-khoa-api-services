<?php
// src/Utils/FileUploader.php

namespace App\Utils;

class FileUploader
{
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxSize;

    public function __construct(array $config = [])
    {
        $this->uploadDir = $config['upload_dir'] ?? __DIR__ . '/../../uploads';
        $this->allowedTypes = $config['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxSize = $config['max_size'] ?? 15 * 1024 * 1024; // 15MB
    }

    public function handle(array $file, string $subfolder = ''): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Lỗi trong quá trình tải file lên. Mã lỗi: ' . $file['error']];
        }
        if (!in_array($file['type'], $this->allowedTypes)) {
            return ['error' => 'Định dạng file không được phép.'];
        }
        if ($file['size'] > $this->maxSize) {
            return ['error' => 'Kích thước file vượt quá giới hạn ' . ($this->maxSize / 1024 / 1024) . 'MB.'];
        }

        $targetDir = rtrim($this->uploadDir . '/' . ltrim($subfolder, '/'), '/');
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0777, true)) {
                return ['error' => 'Không thể tạo thư mục lưu trữ file.'];
            }
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $url = '/uploads/' . ltrim($subfolder, '/') . '/' . $newFileName;
            return ['url' => str_replace('//', '/', $url)];
        }

        return ['error' => 'Không thể lưu trữ file đã tải lên.'];
    }
}