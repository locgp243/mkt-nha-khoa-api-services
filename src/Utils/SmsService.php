<?php
// src/Utils/SmsService.php

namespace App\Utils;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class SmsService
{
    private ?HttpClient $httpClient = null;
    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private string $apiUrl = 'http://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post';
    private ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->apiKey = $_ENV['ESMS_API_KEY'] ?? null;
        $this->secretKey = $_ENV['ESMS_SECRET_KEY'] ?? null;
        $this->logger = $logger;

        if ($this->apiKey && $this->secretKey) {
            $this->httpClient = new HttpClient(['timeout' => 15.0]);
        }
    }

    /**
     * Validate phone number format
     */
    private function validatePhoneNumber(string $phoneNumber): bool
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^\d]/', '', $phoneNumber);
        
        // Check Vietnam phone number format
        return preg_match('/^(0|84)(3|5|7|8|9)\d{8}$/', $phone);
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $phone = preg_replace('/[^\d]/', '', $phoneNumber);
        
        if (str_starts_with($phone, '0')) {
            return '84' . substr($phone, 1);
        }
        
        if (!str_starts_with($phone, '84')) {
            return '84' . $phone;
        }
        
        return $phone;
    }

    /**
     * Gửi tin nhắn OTP qua eSMS.vn
     */
    public function sendOtp(string $phoneNumber, string $otpCode, string $brandName = 'MayDental'): array
    {
        if (!$this->httpClient) {
            $error = "eSMS.vn credentials are not configured in .env file.";
            $this->log('error', $error);
            return ['success' => false, 'message' => $error];
        }

        if (!$this->validatePhoneNumber($phoneNumber)) {
            $error = "Invalid phone number format: {$phoneNumber}";
            $this->log('warning', $error);
            return ['success' => false, 'message' => 'Số điện thoại không hợp lệ'];
        }

        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        $content = "[{$brandName}] Ma xac thuc cua ban la: " . $otpCode;

        $postData = [
            'ApiKey'      => $this->apiKey,
            'SecretKey'   => $this->secretKey,
            'Content'     => $content,
            'Phone'       => $normalizedPhone,
            'SmsType'     => '2', // OTP message type
            'IsUnicode'   => '0',
            'Brandname'   => 'test'
        ];

        try {
            $this->log('info', "Sending OTP to: {$normalizedPhone}");
            
            $response = $this->httpClient->post($this->apiUrl, [
                'form_params' => $postData,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['CodeResult']) && $body['CodeResult'] == 100) {
                $this->log('info', "OTP sent successfully to: {$normalizedPhone}");
                return [
                    'success' => true, 
                    'message' => 'SMS đã được gửi thành công',
                    'sms_id' => $body['SMSID'] ?? null
                ];
            } else {
                $errorMessage = $body['ErrorMessage'] ?? 'Unknown error';
                $this->log('error', "eSMS.vn API Error: " . $errorMessage);
                return [
                    'success' => false, 
                    'message' => 'Không thể gửi SMS: ' . $errorMessage,
                    'error_code' => $body['CodeResult'] ?? null
                ];
            }

        } catch (GuzzleException $e) {
            $error = "GuzzleException when calling eSMS.vn API: " . $e->getMessage();
            $this->log('error', $error);
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $this->log('error', "eSMS.vn Error Response Body: " . $responseBody);
            }
            
            return [
                'success' => false, 
                'message' => 'Lỗi kết nối khi gửi SMS'
            ];
        }
    }

    /**
     * Gửi SMS thông thường (không phải OTP)
     */
    public function sendSms(string $phoneNumber, string $message, string $brandName = 'MayDental'): array
    {
        if (!$this->httpClient) {
            $error = "eSMS.vn credentials are not configured in .env file.";
            $this->log('error', $error);
            return ['success' => false, 'message' => $error];
        }

        if (!$this->validatePhoneNumber($phoneNumber)) {
            $error = "Invalid phone number format: {$phoneNumber}";
            $this->log('warning', $error);
            return ['success' => false, 'message' => 'Số điện thoại không hợp lệ'];
        }

        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        $content = "[{$brandName}] " . $message;

        $postData = [
            'ApiKey'      => $this->apiKey,
            'SecretKey'   => $this->secretKey,
            'Content'     => $content,
            'Phone'       => $normalizedPhone,
            'SmsType'     => '1', // Regular message type
            'IsUnicode'   => '0'
        ];

        try {
            $this->log('info', "Sending SMS to: {$normalizedPhone}");
            
            $response = $this->httpClient->post($this->apiUrl, [
                'form_params' => $postData
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['CodeResult']) && $body['CodeResult'] == 100) {
                $this->log('info', "SMS sent successfully to: {$normalizedPhone}");
                return [
                    'success' => true, 
                    'message' => 'SMS đã được gửi thành công',
                    'sms_id' => $body['SMSID'] ?? null
                ];
            } else {
                $errorMessage = $body['ErrorMessage'] ?? 'Unknown error';
                $this->log('error', "eSMS.vn API Error: " . $errorMessage);
                return [
                    'success' => false, 
                    'message' => 'Không thể gửi SMS: ' . $errorMessage
                ];
            }

        } catch (GuzzleException $e) {
            $error = "GuzzleException when calling eSMS.vn API: " . $e->getMessage();
            $this->log('error', $error);
            
            return [
                'success' => false, 
                'message' => 'Lỗi kết nối khi gửi SMS'
            ];
        }
    }

    /**
     * Helper method for logging
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message);
        } else {
            error_log("[{$level}] {$message}");
        }
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->httpClient !== null;
    }
}