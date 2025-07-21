<?php
// src/Controllers/Public/PublicStaticPageController.php

namespace App\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Models\StaticPage;

class PublicStaticPageController
{
    private StaticPage $staticPageModel;

    public function __construct(StaticPage $staticPageModel)
    {
        $this->staticPageModel = $staticPageModel;
    }

    /**
     * Lấy chi tiết một trang tĩnh bằng slug (GET /api/public/pages/{slug})
     */
    public function show(Request $request, string $slug): Response
    {
        $page = $this->staticPageModel->findBySlug($slug);
        if (!$page) {
            return new Response(['message' => 'Trang không tồn tại.'], 404);
        }
        return new Response(['data' => $page]);
    }
}