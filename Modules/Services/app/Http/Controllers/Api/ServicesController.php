<?php

namespace Modules\Services\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Services\Models\Service;

class ServicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::query()
            ->where('status', 'published')
            ->when($request->filled('category'), fn ($query) => $query->where('service_category_id', $request->input('category')))
            ->with('category')
            ->latest()
            ->paginate(12);

        return response()->json($services->through(fn (Service $service) => [
            'id' => $service->id,
            'title' => $service->title,
            'category' => $service->category->name,
            'fulfillment_type' => $service->category->fulfillment_type,
            'price' => $service->price()->toDecimal(),
            'currency' => $service->currency,
        ]));
    }

    public function show(Service $service): JsonResponse
    {
        abort_unless($service->status === 'published', 404);

        $service->load(['category', 'provider']);

        return response()->json([
            'id' => $service->id,
            'title' => $service->title,
            'description' => $service->description,
            'category' => $service->category->name,
            'fulfillment_type' => $service->category->fulfillment_type,
            'price' => $service->price()->toDecimal(),
            'currency' => $service->currency,
            'provider' => ['name' => $service->provider->name],
        ]);
    }
}
