<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EventsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'module' => 'Events',
            'status' => 'ok',
        ]);
    }
}
