# Route dan Endpoint

Tidak ada `routes/api.php`; endpoint berikut didefinisikan pada `routes/web.php` dan tetap memakai session auth.

## Endpoint
- `GET /login`, `POST /login`, `POST /logout`
- `GET /attendance`, `GET /attendance/export`
- `GET|POST /classes/{class}/students`, `POST /classes/{class}/students/import`, `DELETE /classes/{class}/students/{student}`, `GET /students/template`
- `GET|POST|PUT /coach/reports*`, `GET /coach/students`
- `/admin/dashboard`, `/admin/users*`, `/admin/reports*`, `/admin/schools*`, `/admin/classes*`, `/admin/programs*`, `/admin/coaches*`
- `GET /pic/dashboard`, `GET /pic/reports/{report}`
- `GET /media/{media}`
- `GET /api/classes/{class}/students`

Route name dan permission aktual harus dibaca dari `routes/web.php`. Endpoint AJAX tetap memanggil `AuthorizationService::canAccessClass`. Tidak ada klaim API token, OpenAPI, atau JSON API umum yang terverifikasi.
