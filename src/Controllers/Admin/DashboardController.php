<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\Customer; // Sử dụng Customer model

class DashboardController
{
    private Customer $customerModel;

    public function __construct(Customer $customerModel)
    {
        $this->customerModel = $customerModel;
    }

    /**
     * Lấy tất cả dữ liệu thống kê cho trang Dashboard.
     */
    public function getStatistics(Request $request): Response
    {
        $params = $request->getBody();
        $chartPeriod = $params['period'] ?? 'day'; // 'day', 'month', 'year'

        $data = [
            'summaryData' => [
                'newSignups' => $this->customerModel->getNewRegistrationsCount('1 MONTH'),
                // Các thống kê khác như 'visitors', 'conversionRate' sẽ cần các model hoặc dịch vụ khác (vd: Google Analytics)
                // Tạm thời hardcode các giá trị này
                'visitors' => 1420,
                'conversionRate' => 5.8,
            ],
            'registrationChartData' => $this->customerModel->getRegistrationStatsByPeriod($chartPeriod),
            'recentTrials' => $this->customerModel->getRecentRegistrations(5)
        ];

        return new Response($data);
    }
}