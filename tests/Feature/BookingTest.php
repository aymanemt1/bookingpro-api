<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function nextWeekday(): string
    {
        $date = now()->addDays(2)->startOfDay();
        while ($date->isSunday()) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    private function book(array $overrides = []): array
    {
        $service = $overrides['service'] ?? Service::factory()->create([
            'duration_minutes' => 60,
        ]);

        return array_merge([
            'service_id' => $service->id,
            'date' => $this->nextWeekday(),
            'start_time' => '10:00',
            'customer_name' => 'Aymane Moutoute',
            'customer_phone' => '+212600000000',
        ], $overrides);
    }

    public function test_availability_returns_slots_on_open_day(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);

        $response = $this->getJson("/api/services/{$service->id}/availability?date={$this->nextWeekday()}");

        $response->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonCount(17, 'slots'); // 09:00–17:00 starts, 60-min slots

        $this->assertTrue(collect($response->json('slots'))->every(fn ($s) => $s['available']));
    }

    public function test_availability_closed_on_sunday(): void
    {
        $service = Service::factory()->create();
        $sunday = now()->next(Carbon::SUNDAY)->toDateString();

        $this->getJson("/api/services/{$service->id}/availability?date={$sunday}")
            ->assertOk()
            ->assertJsonPath('open', false)
            ->assertJsonCount(0, 'slots');
    }

    public function test_availability_keeps_later_slots_open_on_the_current_day(): void
    {
        $this->travelTo('2026-10-14 10:15:00');

        $service = Service::factory()->create(['duration_minutes' => 60]);

        $today = $this->getJson("/api/services/{$service->id}/availability?date=2026-10-14");

        $today->assertOk()
            ->assertJsonPath('open', true)
            ->assertJsonFragment(['start' => '09:00', 'end' => '10:00', 'available' => false])
            ->assertJsonFragment(['start' => '10:00', 'end' => '11:00', 'available' => false])
            ->assertJsonFragment(['start' => '10:30', 'end' => '11:30', 'available' => true])
            ->assertJsonFragment(['start' => '11:00', 'end' => '12:00', 'available' => true]);

        $this->getJson("/api/services/{$service->id}/availability?date=2026-10-13")
            ->assertOk()
            ->assertJsonCount(0, 'slots');
    }

    public function test_guest_can_book_a_later_slot_on_the_current_day(): void
    {
        $this->travelTo('2026-10-14 10:15:00');

        $service = Service::factory()->create(['duration_minutes' => 60]);

        $response = $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'date' => '2026-10-14',
            'start_time' => '11:00',
            'customer_name' => 'Aymane Moutoute',
            'customer_phone' => '+212600000000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.date', '2026-10-14')
            ->assertJsonPath('data.start_time', '11:00')
            ->assertJsonPath('data.end_time', '12:00');

        $this->assertDatabaseHas('bookings', [
            'service_id' => $service->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $booking = Booking::sole();
        $this->assertSame('2026-10-14', $booking->date->toDateString());
        $this->assertSame('11:00', substr($booking->start_time, 0, 5));
        $this->assertSame('12:00', substr($booking->end_time, 0, 5));
    }

    public function test_started_slot_on_the_current_day_is_rejected(): void
    {
        $this->travelTo('2026-10-14 10:15:00');

        $service = Service::factory()->create(['duration_minutes' => 60]);

        $this->postJson('/api/bookings', [
            'service_id' => $service->id,
            'date' => '2026-10-14',
            'start_time' => '09:00',
            'customer_name' => 'Aymane Moutoute',
            'customer_phone' => '+212600000000',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This time slot is no longer available.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_guest_can_create_booking(): void
    {
        $payload = $this->book();

        $response = $this->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '11:00');

        $this->assertDatabaseHas('bookings', [
            'customer_phone' => '+212600000000',
            'user_id' => null,
        ]);
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $payload = $this->book();
        $this->postJson('/api/bookings', $payload)->assertCreated();

        // Same slot
        $this->postJson('/api/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This time slot is no longer available.');

        // Partial overlap (10:30–11:30 overlaps 10:00–11:00)
        $this->postJson('/api/bookings', array_merge($payload, ['start_time' => '10:30']))
            ->assertUnprocessable();

        // Adjacent slot is fine (11:00–12:00)
        $this->postJson('/api/bookings', array_merge($payload, ['start_time' => '11:00']))
            ->assertCreated();
    }

    public function test_booking_outside_hours_is_rejected(): void
    {
        $payload = $this->book(['start_time' => '08:00']);

        $this->postJson('/api/bookings', $payload)->assertUnprocessable();

        // Slot that would end after closing (17:30 + 60min > 18:00)
        $payload = $this->book(['start_time' => '17:30']);
        $this->postJson('/api/bookings', $payload)->assertUnprocessable();
    }

    public function test_past_date_is_rejected(): void
    {
        $payload = $this->book(['date' => now()->subDay()->toDateString()]);

        $this->postJson('/api/bookings', $payload)->assertUnprocessable();
    }

    public function test_customer_can_view_and_cancel_own_booking(): void
    {
        $user = User::factory()->create();
        $payload = $this->book();
        $id = $this->actingAs($user)->postJson('/api/bookings', $payload)
            ->assertCreated()->json('data.id');

        $this->actingAs($user)->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->postJson("/api/bookings/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals('cancelled', Booking::find($id)->status);
    }

    public function test_customer_cannot_cancel_someone_elses_booking(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $id = $this->actingAs($userA)->postJson('/api/bookings', $this->book())
            ->assertCreated()->json('data.id');

        $this->actingAs($userB)->postJson("/api/bookings/{$id}/cancel")
            ->assertForbidden();
    }

    public function test_guest_can_lookup_booking_by_reference(): void
    {
        $number = $this->postJson('/api/bookings', $this->book())
            ->assertCreated()->json('data.booking_number');

        $this->getJson("/api/bookings/reference/{$number}")
            ->assertOk()
            ->assertJsonPath('data.booking_number', $number);

        $this->getJson('/api/bookings/reference/BK-NOPE-000000')->assertNotFound();
    }

    public function test_admin_can_manage_bookings_and_services(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $id = $this->actingAs($user)->postJson('/api/bookings', $this->book())
            ->assertCreated()->json('data.id');

        // Admin dashboard
        $this->actingAs($admin)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['today_count', 'pending_count', 'total_count']);

        // Admin booking list
        $this->actingAs($admin)->getJson('/api/admin/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Update status
        $this->actingAs($admin)->patchJson("/api/admin/bookings/{$id}/status", [
            'status' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        // Regular user blocked from admin routes
        $this->actingAs($user)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->actingAs($user)->postJson('/api/services', ['name' => 'X'])->assertForbidden();
    }

    public function test_admin_service_crud(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $create = $this->actingAs($admin)->postJson('/api/services', [
            'name' => 'Hot Stone Massage',
            'duration_minutes' => 90,
            'price' => 450,
        ]);
        $create->assertCreated()->assertJsonPath('data.slug', 'hot-stone-massage');
        $id = $create->json('data.id');

        $this->actingAs($admin)->patchJson("/api/services/{$id}", ['price' => 500])
            ->assertOk()->assertJsonPath('data.price', '500.00');

        $this->actingAs($admin)->deleteJson("/api/services/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('services', ['id' => $id]);
    }
}
