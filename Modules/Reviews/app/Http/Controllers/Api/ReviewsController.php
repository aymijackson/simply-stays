<?php

namespace Modules\Reviews\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReviewsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'module' => 'Reviews',
            'status' => 'ok',
        ]);
    }
}
