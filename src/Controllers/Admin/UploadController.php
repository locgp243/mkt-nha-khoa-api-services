<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Utils\FileUploader;

class UploadController
{
    private FileUploader $fileUploader;

    public function __construct(FileUploader $fileUploader)
    {
        $this->fileUploader = $fileUploader;
    }

    public function upload(Request $request): Response
    {
        // 1. Lấy file từ Request object, không dùng trực tiếp $_FILES
        $uploadedFile = $request->getFile('file');

        if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return new Response(['message' => 'Không có file nào được tải lên hoặc có lỗi xảy ra.'], 400);
        }

        // 2. Lấy subfolder từ dữ liệu POST, cung cấp giá trị mặc định là 'images'
        $subfolder = $request->getFromPost('subfolder', 'images');

        // 3. (Cực kỳ quan trọng) Lọc đầu vào để chống lại lỗi bảo mật Directory Traversal
        // Chỉ cho phép ký tự an toàn trong tên thư mục
        $safeSubfolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $subfolder);
        if (empty($safeSubfolder)) {
            $safeSubfolder = 'images'; // Đảm bảo không bao giờ là chuỗi rỗng
        }

        // 4. Gọi service để xử lý file với tên thư mục đã được làm sạch
        $result = $this->fileUploader->handle($uploadedFile, $safeSubfolder);

        if (isset($result['error'])) {
            return new Response(['message' => $result['error']], 400);
        }

        return new Response(['url' => $result['url']]);
    }
}