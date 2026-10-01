{{--
    Buka kembali modal form setelah validasi server gagal.

    MASALAH: form tambah/edit di aplikasi ini berada di dalam modal Bootstrap.
    Saat validasi gagal, controller melakukan `back()->withInput()->withErrors()`
    sehingga halaman daftar dirender ulang — tetapi modalnya TERTUTUP. Pesan
    error dan isian lama karena itu tidak terlihat sama sekali, dan user hanya
    melihat daftar yang tampak tidak berubah. Ia menyimpulkan "tombol simpan
    tidak bekerja".

    Pemakaian (di dalam @section('scripts') halaman daftar):

        @include('partials.modal-reopen', ['modalId' => 'addCoachModal'])
        @include('partials.modal-reopen', ['modalId' => 'editCoachModal', 'when' => old('_modal') === 'edit'])

    Tanpa `when`, modal dibuka kembali setiap kali ada old input. Untuk halaman
    yang punya beberapa modal (tambah + edit), kirim `when` agar hanya modal
    yang benar yang terbuka — biasanya lewat penanda tersembunyi di form:

        <input type="hidden" name="_modal" value="edit">

    @param string      $modalId ID elemen modal Bootstrap.
    @param bool|string $when    Syarat tambahan (opsional).
--}}
@php
    $when = $when ?? true;
@endphp

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Tidak ada old input = halaman baru dibuka biasa, bukan hasil redirect
        // validasi. Modal harus tetap tertutup.
        var hasOldInput = @json(old() !== []);
        var shouldOpen = @json((bool) $when);

        if (!hasOldInput || !shouldOpen) {
            return;
        }

        var modal = document.getElementById(@json($modalId));

        if (modal && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(modal).show();
        }
    });
</script>
