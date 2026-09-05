# Proses Bisnis

## Setup
Relation/SuperAdmin mengelola School, Program, Class, Student, user, serta data Coach. SPV Coach mengelola akun Coach dan assignment Coach ke class.

## Coach Report
1. Coach membuka class yang ditugaskan.
2. Coach membuat report, mengisi tanggal/materi/aktivitas/catatan, attendance siswa, dan media.
3. Coach menyimpan draft atau submit; submit mengubah status menjadi `submitted`.
4. Relation atau SuperAdmin meninjau report submitted.
5. Reviewer memilih approve atau reject. Reject membutuhkan `admin_notes`.
6. Coach hanya dapat mengedit `draft` atau `rejected`; setelah perbaikan submit kembali menjadi `submitted`.
7. Approve mencatat reviewer dan waktu approval.

## Attendance Scope
Attendance berasal dari report. Relation/SuperAdmin/SPV Coach bersifat global sesuai capability. Coach hanya report sendiri. PIC DK SCHOOL, TEACHER SCHOOL, dan Finance hanya sekolah yang diplot dan report approved. Filter school/class/date/status tidak dapat memperluas scope backend.

## School View
PIC memiliki dashboard report approved dan attendance/export. TEACHER SCHOOL memiliki report browse dan attendance/export melalui view bersama. Finance memiliki attendance dan CSV export sesuai capability; tidak ada dashboard Finance terpisah.

## Media
Media baru disimpan oleh `MediaStorageService` pada local private disk dan hanya disajikan melalui route terotorisasi. Cloudinary URL lama dapat tetap dibaca dan dapat diproses command migrasi legacy.
