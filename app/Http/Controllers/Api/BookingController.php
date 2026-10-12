<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Service;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        AvailabilityService $availability
    ): JsonResponse {
        $service = Service::active()->findOrFail($request->service_id);
        $date = $request->date;
        $startTime = $request->start_time;

        abort_unless(
            $availability->isSlotFree($service, $date, $startTime),
            422,
            'This time slot is no longer available.'
        );

        $start = Carbon::parse("$date $startTime");
        $end = $start->copy()->addMinutes($service->duration_minutes);

        $booking = Booking::create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
            'end_time' => $end->format('H:i:s'),
            'status' => Booking::STATUS_PENDING,
        ]);

        return (new BookingResource($booking->load('service')))->response()->setStatusCode(201);
    }

    /** Public lookup by booking reference (for guests) */
    public function byReference(string $bookingNumber): BookingResource
    {
        $booking = Booking::where('booking_number', $bookingNumber)->firstOrFail();

        return new BookingResource($booking->load('service'));
    }

    /** Bookings of the authenticated user */
    public function myBookings(Request $request): AnonymousResourceCollection
    {
        $bookings = $request->user()->bookings()
            ->with('service')
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->canAccess($request, $booking), 403);

        return new BookingResource($booking->load('service'));
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($this->canAccess($request, $booking), 403);
        abort_unless($booking->isCancellable(), 422, 'This booking can no longer be cancelled.');

        $booking->update(['status' => Booking::STATUS_CANCELLED]);

        return (new BookingResource($booking->fresh('service')))->response();
    }

    /** Admin: all bookings, filterable by date and status */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Booking::with('service')->orderBy('date')->orderBy('start_time');

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return BookingResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    /** Admin: today's agenda + quick stats */
    public function dashboard(): JsonResponse
    {
        $today = now()->toDateString();

        $todays = Booking::with('service')
            ->whereDate('date', $today)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'date' => $today,
            'today_count' => $todays->count(),
            'pending_count' => Booking::where('status', Booking::STATUS_PENDING)->count(),
            'total_count' => Booking::count(),
            'today' => BookingResource::collection($todays),
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): BookingResource
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', Booking::STATUSES)],
        ]);

        $booking->update($data);

        return new BookingResource($booking->fresh('service'));
    }

    private function canAccess(Request $request, Booking $booking): bool
    {
        $user = $request->user();

        return $user->isAdmin() || ($booking->user_id && $booking->user_id === $user->id);
    }
}
