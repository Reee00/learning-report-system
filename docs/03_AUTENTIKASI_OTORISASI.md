# Autentikasi dan Otorisasi

Login dan logout menggunakan session authentication Laravel. Route terlindungi oleh `auth`, lalu middleware `role`, `permission`, atau `permission_any` sesuai kebutuhan.

`AuthorizationService` adalah source of truth capability. SuperAdmin wildcard; role lain memakai daftar capability eksplisit. Capability utama meliputi `schools.*`, `programs.*`, `program_classes.*`, `coaches.*`, `students.*`, `reports.view`, `reports.view_all`, `reports.create`, `reports.update`, `reports.review`, `attendance.view`, `attendance.export`, dan `attendance.export_csv`.

School scope:
- SuperAdmin, Relation, SPV Coach: global operasional.
- Coach: report milik sendiri dan class yang ada pada `coach_classes`.
- PIC DK SCHOOL, TEACHER SCHOOL, Finance: school yang diplot melalui pivot `school_user`, ditambah `users.school_id` legacy; report/attendance yang ditampilkan harus approved.

Scope selalu diperiksa di backend. `admin/*` bukan bukti adanya role `admin`.
