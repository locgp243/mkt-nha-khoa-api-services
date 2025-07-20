<?php
// src/Controllers/Public/PublicPricingPackageController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\PricingPackage;


class PublicPricingPackageController
{
    private PricingPackage $packageModel;


    public function __construct(PricingPackage $packageModel)
    {
        $this->packageModel = $packageModel;
    }

    public function index(Request $request): Response
    {
        $options = $request->getBody();
        $packages = $this->packageModel->getAll($options);
        $total = $this->packageModel->getTotalCount($options);

        return new Response([
            'data' => $packages,
            'pagination' => [
                'total_records' => $total,
                'page' => (int) ($options['page'] ?? 1),
                'limit' => (int) ($options['limit'] ?? 10),
                'total_pages' => ($options['limit'] ?? 10) > 0 ? ceil($total / ($options['limit'] ?? 10)) : 0,
            ]
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $package = $this->packageModel->findById($id);
        if (!$package) {
            return new Response(['message' => 'Gói giá không tồn tại.'], 404);
        }
        return new Response(['data' => $package]);
    }
}