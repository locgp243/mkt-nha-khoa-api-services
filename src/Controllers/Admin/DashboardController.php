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
        $chartPeriod = $params['period'] ?? 'day';
        $year = isset($params['year']) ? (int) $params['year'] : null;

        $signupComparison = $this->customerModel->getNewRegistrationsCountWithComparison();

        $data = [
            'summaryData' => [
                'newSignups' => $signupComparison['thisMonthCount'],
                'signupGrowth' => $signupComparison['percentageChange'],
                'isSignupGrowth' => $signupComparison['isGrowth'],
                'visitors' => 0, // Dữ liệu ảo
                'conversionRate' => 0, // Dữ liệu ảo
            ],
            'registrationChartData' => $this->customerModel->getRegistrationStatsByPeriod($chartPeriod, $year),
            'recentTrials' => $this->customerModel->getRecentRegistrations(5)
        ];

        return new Response($data);
    }
}