<?php
// config/database.php

/**
 * Hàm kết nối cơ sở dữ liệu một cách an toàn và tập trung.
 *
 * @param array $config Mảng chứa thông tin kết nối từ file config/app.php
 * (bao gồm host, dbname, user, pass, port, charset).
 * @return mysqli Đối tượng kết nối mysqli đã sẵn sàng để sử dụng.
 *
 * @throws mysqli_sql_exception Nếu không thể kết nối đến cơ sở dữ liệu.
 */
function getDbConnection(array $config): mysqli
{
    // Tắt báo cáo lỗi mặc định của PHP để chúng ta có thể tự bắt và xử lý exception.
    // Điều này giúp ứng dụng không bị lộ thông tin lỗi nhạy cảm ra ngoài.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // Tạo đối tượng kết nối mysqli từ mảng cấu hình.
        $conn = new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['dbname'],
            (int) $config['port']
        );

        // Thiết lập bộ mã (charset) cho kết nối để đảm bảo dữ liệu tiếng Việt
        // được đọc và ghi một cách chính xác.
        $conn->set_charset($config['charset']);

        // Trả về đối tượng kết nối nếu thành công.
        return $conn;

    } catch (mysqli_sql_exception $e) {
        // Nếu có lỗi kết nối, chúng ta sẽ không tiếp tục chạy ứng dụng.
        // Thay vào đó, ghi lỗi vào log của server để lập trình viên có thể xem.
        error_log("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());

        // Và trả về một thông báo lỗi JSON thân thiện cho người dùng cuối.
        // Tuyệt đối không hiển thị chi tiết lỗi của database ra ngoài.
        header('Content-Type: application/json');
        http_response_code(503); // Service Unavailable
        echo json_encode([
            'status' => 'error',
            'message' => 'Hệ thống đang bảo trì, vui lòng thử lại sau.'
        ]);
        // Dừng hoàn toàn việc thực thi script.
        exit;
    }
}