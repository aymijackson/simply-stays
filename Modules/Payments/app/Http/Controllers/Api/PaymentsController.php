<?php

namespace Modules\Payments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PaymentsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'module' => 'Payments',
            'status' => 'ok',
        ]);
    }
}
