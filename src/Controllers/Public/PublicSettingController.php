<?php
// src/Controllers/Public/SettingController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\SiteSetting;

class PublicSettingController
{
    private SiteSetting $siteSettingModel;

    public function __construct(SiteSetting $siteSettingModel)
    {
        $this->siteSettingModel = $siteSettingModel;
    }

    /**
     * Lấy các cài đặt công khai (GET /api/public/settings)
     */
    public function index(Request $request): Response
    {
        $publicSettings = $this->siteSettingModel->getPublicSettings();
        return new Response($publicSettings);
    }
}