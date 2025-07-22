<?php
namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;

class ActivityLogController
{
    private ActivityLog $logModel;

    public function __construct(ActivityLog $logModel)
    {
        $this->logModel = $logModel;
    }

    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $logs = $this->logModel->getAll($options);
        $total = $this->logModel->getTotalCount($options);

        // Giải mã chuỗi JSON trong 'details' để frontend dễ sử dụng
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }

        return new Response([
            'data' => $logs,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 15),
                'total_pages' => ($options['limit'] ?? 15) > 0 ? ceil($total / ($options['limit'] ?? 15)) : 0,
            ]
        ]);
    }
}