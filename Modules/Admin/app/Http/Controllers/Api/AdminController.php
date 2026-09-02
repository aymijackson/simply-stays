<?php

namespace Modules\Admin\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'module' => 'Admin',
            'status' => 'ok',
        ]);
    }
}
