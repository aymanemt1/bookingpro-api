<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@booking.test',
            'password' => 'password',
            'is_admin' => true,
        ]);

        User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@booking.test',
            'password' => 'password',
        ]);

        $services = [
            ['name' => 'Classic Haircut', 'duration_minutes' => 30, 'price' => 120, 'description' => 'Precision cut, wash and style.'],
            ['name' => 'Beard Trim & Shape', 'duration_minutes' => 30, 'price' => 80, 'description' => 'Hot towel, trim and razor finish.'],
            ['name' => 'Deep Tissue Massage', 'duration_minutes' => 60, 'price' => 350, 'description' => 'Full-body deep tissue massage to release tension.'],
            ['name' => 'Signature Facial', 'duration_minutes' => 60, 'price' => 400, 'description' => 'Cleanse, exfoliate, mask and hydration ritual.'],
            ['name' => 'Manicure & Pedicure', 'duration_minutes' => 90, 'price' => 300, 'description' => 'Complete nail care with polish.'],
            ['name' => 'Hair Coloring', 'duration_minutes' => 120, 'price' => 600, 'description' => 'Full coloring with premium products.'],
        ];

        foreach ($services as $service) {
            Service::create([
                ...$service,
                'slug' => Str::slug($service['name']),
                'image_url' => 'https://picsum.photos/seed/' . Str::slug($service['name']) . '/800/600',
                'is_active' => true,
            ]);
        }

        $this->command->info('Seeded admin: admin@booking.test / password');
        $this->command->info('Seeded customer: customer@booking.test / password');
    }
}
