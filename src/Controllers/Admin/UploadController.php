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
        // Lưu ý: Request object hiện chưa hỗ trợ lấy file, ta tạm dùng $_FILES
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $result = $this->fileUploader->handle($_FILES['file'], 'images');
            if (isset($result['error'])) {
                return new Response(['message' => $result['error']], 400);
            }
            return new Response(['url' => $result['url']]);
        }
        return new Response(['message' => 'Không có file nào được tải lên.'], 400);
    }
}