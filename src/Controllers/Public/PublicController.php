<?php
// src/Controllers/Public/PublicController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\PricingPackage;
use App\Models\Post;

class PublicController
{
    private Customer $customerModel;
    private PricingPackage $pricingPackageModel;
    private Post $postModel;

    public function __construct(Customer $customerModel, PricingPackage $pricingPackageModel, Post $postModel)
    {
        $this->customerModel = $customerModel;
        $this->pricingPackageModel = $pricingPackageModel;
        $this->postModel = $postModel;
    }

    public function getPricingPackages(Request $request): Response
    {
        $packages = $this->pricingPackageModel->getPublicPackages();
        return new Response(['packages' => $packages]);
    }

    // ... Tái cấu trúc các phương thức khác của PublicController ...
}