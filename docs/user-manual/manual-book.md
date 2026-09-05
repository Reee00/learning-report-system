# Manual Pengguna

## Role
SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. Menu yang tampil bergantung pada capability.

## Alur Penggunaan
Relation/SuperAdmin menyiapkan School, Program, Class, Student, dan user. SPV Coach membuat assignment Coach. Coach membuka class assignment, mengisi report, attendance, dan media, lalu submit. Relation atau SuperAdmin approve/reject. Report rejected diperbaiki Coach lalu disubmit ulang. Pengguna sekolah hanya melihat data school yang diplot dan report approved.

## Attendance dan Export
Attendance tersedia melalui menu `/attendance` sesuai scope. Export tersedia CSV dan PDF sesuai capability; Finance memiliki CSV export. Filter tidak dapat melewati school scope.

## Media
Media report diunggah ke penyimpanan lokal privat dan dibuka melalui akses aplikasi. Jangan mengakses atau memindahkan file langsung dari public storage.

## Catatan
Nama URL `/admin/*` adalah namespace kompatibilitas. Tidak ada menu PWA atau modul Accident Notes terpisah. Nilai URL, credential, dan konfigurasi deployment mengikuti administrator environment.
