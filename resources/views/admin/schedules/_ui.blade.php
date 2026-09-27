@once
@push('styles')
{{--
    Gaya bersama modul Jadwal Mengajar (form pola, daftar pola, detail pola).

    Hanya tata letak: memakai variabel app shell (--primary, --border-color,
    --radius-*, --bg-surface) tanpa memperkenalkan design system baru. Tidak ada
    aturan di sini yang mengubah perilaku form; kelas .sched-* murni presentasi.
--}}
<style>
    /* ---------- Bagian (section) di dalam kartu ---------- */
    .sched-section + .sched-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
    .sched-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: .75rem;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .sched-section-title .bi { color: var(--primary); font-size: .95rem; }
    .sched-section-title > .sched-section-hint {
        margin-left: auto;
        font-size: .72rem;
        font-weight: 500;
        letter-spacing: 0;
        text-transform: none;
    }

    /* ---------- Keadaan kosong (garis putus-putus) ---------- */
    /* Bootstrap tidak punya .border-dashed; kelas ini menggantikannya. */
    .sched-empty {
        border: 2px dashed var(--border-color);
        border-radius: var(--radius-md);
        background-color: var(--secondary-bg);
    }

    /* ---------- Bilah aksi yang menempel di bawah ---------- */
    /* Dipakai bersama .action-bar milik app shell, yang sudah mengatur
       penumpukan tombol di layar sempit. */
    .sched-actionbar {
        position: sticky;
        bottom: 0;
        z-index: 1030;
        margin-top: 1rem;
        padding: .75rem 1rem;
        padding-bottom: max(.75rem, env(safe-area-inset-bottom));
        background-color: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        box-shadow: 0 -8px 20px rgba(15, 23, 42, .07);
    }

    /* ---------- Baris kelas pada form pola ---------- */
    .sched-row {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background-color: var(--bg-surface);
    }
    .sched-row + .sched-row { margin-top: .625rem; }
    .sched-row-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        padding: .5rem .625rem;
        border-bottom: 1px solid var(--border-color);
        background-color: var(--secondary-bg);
        border-radius: var(--radius-md) var(--radius-md) 0 0;
    }
    .sched-row-body { padding: .75rem .625rem; }

    /* Chip coach tambahan yang sedang terpilih — supaya pilihan pada
       <select multiple> tetap terlihat walau daftarnya sedang tertutup. */
    .sched-chips:empty { display: none; }

    /* ---------- Sesi nonaktif ---------- */
    /* Sesi nonaktif tetap tampil sebagai riwayat, tetapi dilemahkan supaya
       tidak terbaca sebagai sesi yang benar-benar diajarkan. */
    .sched-row-inactive { opacity: .62; }
    .sched-row-inactive td:first-child { box-shadow: inset 3px 0 0 var(--text-light); }
    .sched-row-inactive:hover { opacity: 1; }

    /* Label kecil "Coach Utama" / "Coach Pendamping" pada sel coach, supaya
       dua coach pada satu sesi tidak tampak setara tanpa keterangan. */
    .sched-coach-role {
        display: block;
        font-size: .62rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        opacity: .75;
    }

    /* ---------- Tabel yang menjadi kartu di layar sempit ---------- */
    /* <thead> disembunyikan dan setiap <td> memakai data-label sebagai
       judul barisnya, sehingga tidak ada tabel yang dipaksa mengecil. */
    @media (max-width: 767.98px) {
        /* Shell memberi min-width pada tabel padat (lewat :has()) agar bisa
           discroll. Di sini tabel justru berubah menjadi kartu, jadi
           min-width itu harus dinetralkan — satu-satunya !important di berkas
           ini, karena spesifisitas :has() tidak bisa dikalahkan secara wajar. */
        .table-responsive .sched-table { min-width: 0 !important; }

        .sched-table thead { display: none; }
        /* tbody juga dijadikan block supaya <tr> sebagai kartu tidak lagi
           bergantung pada perilaku table-row-group di tiap browser. */
        .sched-table tbody { display: block; }

        .sched-table tbody tr {
            display: block;
            padding: .5rem .875rem;
            margin-bottom: .75rem;
            background-color: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
        }
        .sched-table tbody tr:last-child { margin-bottom: 0; }
        .sched-table tbody tr.sched-row-off { background-color: var(--warning-light); }

        /* Warna baris "tidak jalan" dibawa oleh <tr> sebagai kartu, jadi
           lapisan warna milik Bootstrap pada sel dinetralkan di sini. */
        .table-responsive .sched-table tbody tr > td {
            background-color: transparent;
            box-shadow: none;
        }

        .table-responsive .sched-table tbody td {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .875rem;
            padding: .4rem 0;
            border: 0;
            border-bottom: 1px dashed var(--border-color);
            white-space: normal;
            text-align: right;
        }
        .sched-table tbody tr td:last-child { border-bottom: 0; }

        .sched-table tbody td::before {
            content: attr(data-label);
            flex: 0 0 42%;
            text-align: left;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        /* Sel tanpa label (mis. kolom aksi) memakai lebar penuh. */
        .sched-table tbody td:not([data-label])::before { display: none; }
        .sched-table tbody td:not([data-label]) { justify-content: flex-end; }

        .sched-table tbody td > * { min-width: 0; }
        .sched-table tbody td .badge { white-space: normal; text-align: right; }

        /* Tanggal, jam, dan angka tidak boleh terpecah antarbaris. */
        .table-responsive .sched-table tbody td.sched-nw { white-space: nowrap; }

        /* Baris kosong ("belum ada data") tidak perlu tampil sebagai kartu. */
        .sched-table tbody td[colspan] {
            display: block;
            border-bottom: 0;
            text-align: center;
        }
        .sched-table tbody td[colspan]::before { display: none; }
    }
</style>
@endpush
@endonce
