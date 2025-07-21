<?php

namespace App\Controllers\Public;

// Nạp các class cần thiết
use App\Models\Otp;
use App\Utils\SmsService;
use GuzzleHttp\Client as HttpClient; // Dùng Guzzle để gọi API
use GuzzleHttp\Exception\GuzzleException;

/**
 * PublicAuthController
 * * Đây là phiên bản nâng cấp, áp dụng Dependency Injection và các best practices.
 * Đảm bảo em đã cài Guzzle: `composer require guzzlehttp/guzzle`
 */
class PublicAuthController {

    private HttpClient $httpClient;

    /**
     * Sử dụng Dependency Injection để "tiêm" các phụ thuộc vào Controller.
     * Code trở nên linh hoạt, dễ bảo trì và đặc biệt là DỄ TEST.
     * private Otp $otpModel - đây là cú pháp Constructor Property Promotion của PHP 8.
     */
    public function __construct(
        private Otp $otpModel,
        private SmsService $smsService
    ) {
        $this->httpClient = new HttpClient([
            'timeout'  => 5.0, // Set timeout cho các request ra ngoài
        ]);
    }
    
    /**
     * Hàm helper để trả về JSON response một cách nhất quán.
     */
    private function jsonResponse(int $statusCode, array $data): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Xác minh reCAPTCHA token bằng Guzzle - mạnh mẽ và an toàn hơn.
     */
    private function verifyRecaptcha(string $token): bool {
        $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'];
        if (empty($secretKey)) {
            error_log('RECAPTCHA_SECRET_KEY is not set in .env file');
            return false;
        }
        
        try {
            $response = $this->httpClient->post('https://www.google.com/recaptcha/api/siteverify', [
                'form_params' => [
                    'secret'   => $secretKey,
                    'response' => $token,
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            return isset($body['success']) && $body['success'] === true;

        } catch (GuzzleException $e) {
            error_log('GuzzleException when verifying reCAPTCHA: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Xử lý yêu cầu gửi OTP.
     */
    public function handlePhoneNumberVerification(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        $phoneNumber = $data['phoneNumber'] ?? null;
        $captchaToken = $data['captchaToken'] ?? null;

        if (!$phoneNumber || !$captchaToken) {
            $this->jsonResponse(400, ['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }

        // BƯỚC 1: XÁC MINH reCAPTCHA
        if (!$this->verifyRecaptcha($captchaToken)) {
            $this->jsonResponse(403, ['success' => false, 'message' => 'Xác minh reCAPTCHA thất bại.']);
        }

        // BƯỚC 2: CHỐNG SPAM (Rate Limiting)
        // Sử dụng dependency đã được "tiêm" vào, không cần `new`
        if ($this->otpModel->hasRecentRequest($phoneNumber)) {
            $this->jsonResponse(429, ['success' => false, 'message' => 'Bạn đã yêu cầu mã OTP quá nhanh. Vui lòng thử lại sau 1 phút.']);
        }

        // BƯỚC 3: TẠO VÀ GỬI OTP
        $otpCode = $this->otpModel->create($phoneNumber);
        if ($otpCode && $this->smsService->sendOtp($phoneNumber, $otpCode)) {
            $this->jsonResponse(200, ['success' => true, 'message' => 'Mã OTP đã được gửi thành công.']);
        }
        
        $this->jsonResponse(500, ['success' => false, 'message' => 'Lỗi hệ thống, không thể gửi OTP.']);
    }

    /**
     * Xử lý xác minh mã OTP (nhất quán sử dụng JSON).
     */
    public function verifyOtp(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        $phoneNumber = $data['phoneNumber'] ?? null;
        $otpCode = $data['otp'] ?? null;

        if (!$phoneNumber || !$otpCode) {
            $this->jsonResponse(400, ['success' => false, 'message' => 'Vui lòng cung cấp đầy đủ thông tin.']);
        }

        if ($this->otpModel->verify($phoneNumber, $otpCode)) {
            // TODO: Tạo session hoặc JWT token cho user tại đây
            $this->jsonResponse(200, ['success' => true, 'message' => 'Xác thực thành công.']);
        }
        
        $this->jsonResponse(400, ['success' => false, 'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn.']);
    }
}