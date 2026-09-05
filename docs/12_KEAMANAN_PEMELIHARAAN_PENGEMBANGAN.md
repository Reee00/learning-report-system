# Keamanan dan Pemeliharaan

- Gunakan middleware auth/permission dan `AuthorizationService` pada setiap endpoint.
- Enforce school/class/report scope di backend; jangan mengandalkan filter UI.
- Media baru disimpan di private local disk dan disajikan lewat controller yang memeriksa role, report, dan school scope.
- Database menyimpan path/reference dan metadata media, bukan binary.
- Jangan commit `.env`, credential, atau file media.
- Pertahankan foreign-key rules dan transaksi report/attendance/media saat mengubah domain.
- Perlakukan `CloudinaryHelper`, dependency, config, dan URL eksternal sebagai legacy compatibility; jalur upload baru adalah `MediaStorageService`.
- Jalankan test dan review migration sebelum deployment.
- Catat perubahan permission, role, status, dan scope dalam dokumentasi ini serta `contextproject.md`.
