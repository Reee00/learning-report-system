{{--
    Komponen bersama: tombol tampil/sembunyikan password (review meeting LRS 2026-10-01).

    Dipakai halaman Login dan Pengaturan Akun — satu perilaku, satu tempat.

    Cara pakai:
        <div class="input-group">
            <input type="password" id="sandi" name="password" ...>
            <button type="button" class="btn btn-outline-secondary"
                    data-password-toggle="#sandi"
                    aria-controls="sandi" aria-pressed="false"
                    aria-label="Tampilkan password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        @include('partials.password-toggle-scripts')

    Kenapa delegasi di `document`, bukan listener per tombol:
    - tombol yang dirender belakangan (modal, baris tabel) tetap bekerja tanpa
      registrasi ulang;
    - hanya satu listener, dan penjaga `window.__passwordToggleBound` membuat
      partial ini aman di-@include lebih dari sekali di halaman yang sama.

    Nilai password TIDAK pernah disentuh: hanya atribut `type` yang berganti,
    sehingga isi field, autofill, dan password manager browser tetap utuh.
    `type="button"` memastikan tombol tidak pernah men-submit form.
--}}
<script>
(function () {
    if (window.__passwordToggleBound) {
        return;
    }
    window.__passwordToggleBound = true;

    var ICON_HIDDEN = 'bi-eye';
    var ICON_VISIBLE = 'bi-eye-off';

    function label(button, revealed) {
        return button.getAttribute(revealed ? 'data-label-hide' : 'data-label-show')
            || (revealed ? 'Sembunyikan password' : 'Tampilkan password');
    }

    function apply(button, input) {
        var revealed = input.type === 'text';

        // Simpan posisi kursor supaya mengganti tipe tidak memindahkannya.
        var start = null;
        var end = null;
        try {
            start = input.selectionStart;
            end = input.selectionEnd;
        } catch (e) {
            // Sebagian browser menolak membaca selection pada input password.
        }

        input.type = revealed ? 'password' : 'text';

        if (start !== null) {
            try {
                input.setSelectionRange(start, end);
            } catch (e) {
                // Diabaikan: posisi kursor bukan hal kritis.
            }
        }

        var icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle(ICON_HIDDEN, revealed);
            icon.classList.toggle(ICON_VISIBLE, !revealed);
        }

        var text = label(button, revealed);
        button.setAttribute('aria-label', text);
        button.setAttribute('title', text);
        button.setAttribute('aria-pressed', revealed ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-password-toggle]');
        if (!button) {
            return;
        }
        // Berjaga-jaga bila penanda dipasang pada elemen selain <button>.
        event.preventDefault();

        var selector = button.getAttribute('data-password-toggle');
        var input = selector ? document.querySelector(selector) : null;
        if (!input) {
            return;
        }

        apply(button, input);
    });
})();
</script>
