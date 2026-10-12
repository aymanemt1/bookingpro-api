# 📅 BookingPro API

A complete **appointment booking REST API** built with Laravel 12 — services, real availability engine, guest + customer bookings, and an admin dashboard backend.

## ✨ Features

- 🔐 **Sanctum auth** — register, login, logout, profile (+ `is_admin` role)
- 💈 **Services** — CRUD (admin), public catalog with slugs
- 🕐 **Availability engine** — 30-min slot grid per service duration, business hours (Mon–Sat 09:00–18:00, closed Sundays), overlap detection, past-slot hiding
- 📅 **Bookings** — guests can book with name + phone; reference lookup (`BK-20261012-XXXXXX`); customers see their bookings and can cancel
- 👮 **Admin** — dashboard stats + today's agenda, all bookings filterable by date/status, status workflow (`pending → confirmed → completed / cancelled`), service management
- ✅ **Tested** — 13 feature tests (52 assertions): availability, overlaps, validation, guest flow, ownership, admin boundaries

## 🛠️ Stack

Laravel 12 · PHP 8.3 · Sanctum · SQLite/MySQL · PHPUnit

## 🚀 Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
# in .env: DB_CONNECTION=sqlite

php artisan migrate --seed
php artisan serve   # http://localhost:8000
```

Seeded accounts:

| Role     | Email                | Password   |
|----------|----------------------|------------|
| Admin    | admin@booking.test   | `password` |
| Customer | customer@booking.test| `password` |

## 📡 API reference

Base URL: `http://localhost:8000/api`

### Public

| Method | Endpoint                                        | Description                          |
|--------|-------------------------------------------------|--------------------------------------|
| POST   | `/register`, `/login`                           | Auth + token                         |
| GET    | `/services`                                     | Active services                      |
| GET    | `/services/{slug}`                              | Single service                       |
| GET    | `/services/{id}/availability?date=YYYY-MM-DD`   | Slot grid with `available` flags     |
| POST   | `/bookings`                                     | Create booking (guests allowed)      |
| GET    | `/bookings/reference/{number}`                  | Lookup by booking reference          |

### Authenticated

| Method | Endpoint                   | Description              |
|--------|----------------------------|--------------------------|
| GET    | `/me`                      | Profile                  |
| POST   | `/logout`                  | Revoke token             |
| GET    | `/my-bookings`             | Own bookings (paginated) |
| GET    | `/bookings/{id}`           | Booking detail (owner)   |
| POST   | `/bookings/{id}/cancel`    | Cancel own booking       |

### Admin only

| Method | Endpoint                              | Description                          |
|--------|---------------------------------------|--------------------------------------|
| GET    | `/admin/dashboard`                    | Stats + today's agenda               |
| GET    | `/admin/bookings?date=&status=`        | All bookings, filterable             |
| PATCH  | `/admin/bookings/{id}/status`          | Update status                        |
| POST   | `/services`                           | Create service                       |
| PUT    | `/services/{id}`                      | Update service                       |
| DELETE | `/services/{id}`                      | Delete service                       |

## 🧪 Tests

```bash
php artisan test
```

## 📁 Structure

```
app/
├── Http/
│   ├── Controllers/Api/  # Auth, Service, Booking
│   ├── Middleware/       # EnsureUserIsAdmin
│   ├── Requests/         # StoreBooking, Store/UpdateService
│   └── Resources/        # Service, Booking, User
├── Models/               # Service, Booking, User
└── Services/             # AvailabilityService (slot engine)
database/
├── migrations/           # services, bookings, is_admin
└── seeders/              # 6 demo services + demo users
tests/Feature/            # BookingTest (13 tests)
```

---

Built by [Aymane Moutoute](https://github.com/aymanemt1) · Full Stack Software Developer
