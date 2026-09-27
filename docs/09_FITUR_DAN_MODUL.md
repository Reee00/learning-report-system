# Fitur dan Modul

- Authentication: login/logout session.
- Master data: School, Class, Program, Student, User/Coach.
- School Workspace: halaman detail sekolah menjadi satu tempat setup — informasi sekolah, kelas yang ter-assign, dan program per kelas. Bisa membuat kelas baru atau memindahkan kelas master yang masih kosong dari sekolah lain, mengubah nama, memasang/melepas program, dan menghapus kelas yang belum terpakai. Tetap memakai `SchoolClass` + pivot `program_classes`; tidak ada tabel kelas kedua.
- Master Program Kelas: sumber data kelas yang dipakai ulang, dengan kolom Sekolah, Program, dan "Digunakan di" (jumlah murid/jadwal/laporan). Duplikat nama kelas dalam satu sekolah ditolak.
- Coach assignment: assign/reassign Coach ke Class melalui `CoachClass`.
- Student management: CRUD terbatas scope dan import XLSX/XLS/CSV via FastExcel.
- Coach Report: draft, edit, submit, media, attendance, review, approve/reject/resubmit.
- Attendance: query dengan scope role, filter, CSV matrix, dan PDF.
- Teaching Schedule: CRUD sesi, form pola mingguan DIGISchool (tab hari, tiap hari berisi banyak **blok sekolah** dengan **tanggal mulai + jumlah pertemuan sendiri** — mengikuti kolom KET workbook, banyak kelas/coach per blok, duplikat baris/blok, copy pola hari), daftar utama per **pola hari** (default SENIN) dengan detail pertemuan tergenerate, import Excel dua format (workbook perusahaan membaca tanggal mulai dari KET per blok sekolah).
- School portal: PIC dashboard/report approved; TEACHER SCHOOL memakai view bersama; Finance memakai attendance dan CSV capability dengan cakupan seluruh sekolah (all-school).
- Media: local private filesystem, metadata `ReportMedia`, authorized serving, legacy external URL compatibility.
- AJAX: daftar siswa class yang sudah diotorisasi, dan roster murid untuk form jadwal.
- Komponen UI bersama: paginasi Bootstrap 5 berlabel Indonesia (satu view vendor untuk semua pemanggil `->links()`), aturan anti-pecah-angka untuk sel tabel dan badge, form sekolah responsif, nomor baris tabel yang melanjutkan antar halaman.

Belum ada API token service, PWA, permissions table, atau UI terpisah khusus Finance/TEACHER SCHOOL/Relation.
