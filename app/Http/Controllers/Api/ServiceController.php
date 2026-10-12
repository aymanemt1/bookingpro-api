<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ServiceResource::collection(
            Service::active()->withCount('bookings')->orderBy('name')->get()
        );
    }

    public function show(Service $service): ServiceResource
    {
        abort_if(! $service->is_active && ! request()->user()?->isAdmin(), 404);

        return new ServiceResource($service);
    }

    public function availability(Request $request, Service $service, AvailabilityService $availability): JsonResponse
    {
        $request->validate(['date' => ['required', 'date']]);

        return response()->json([
            'date' => $request->date,
            'open' => $availability->isOpenDate(\Carbon\Carbon::parse($request->date)),
            'slots' => $availability->slots($service, $request->date),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return (new ServiceResource($service))->response()->setStatusCode(201);
    }

    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $service->update($request->validated());

        return new ServiceResource($service->fresh());
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }
}
