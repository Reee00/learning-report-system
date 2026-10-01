@php
    // Tombol notifikasi hanya dirender bila Web Push benar-benar dikonfigurasi
    // server-side. Tanpa VAPID public key tidak ada yang bisa di-subscribe, dan
    // tombol mati lebih membingungkan daripada tidak ada tombol sama sekali.
    // Yang dirender HANYA public key — private key tidak pernah meninggalkan server.
    $vapidPublicKey = trim((string) config('webpush.vapid.public_key'));
@endphp

@if($vapidPublicKey !== '')
    <div class="dropdown" id="pushNotifyWrapper">
        <button type="button" class="btn btn-light btn-sm position-relative"
                id="pushNotifyBtn"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                title="Notifikasi perangkat" aria-label="Notifikasi perangkat">
            <i class="bi bi-bell" id="pushNotifyIcon" aria-hidden="true"></i>
            {{-- Titik status: aktif (hijau) / tidak aktif (abu). --}}
            <span id="pushNotifyDot"
                  class="position-absolute top-0 start-100 translate-middle p-1 rounded-circle bg-secondary border border-light d-none"></span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3 mt-2" style="min-width: 280px;">
            <li class="px-3 py-2 border-bottom">
                <div class="fw-semibold" style="font-size: 0.85rem;">Notifikasi Perangkat</div>
                <div class="text-muted" style="font-size: 0.72rem;">Kirim pemberitahuan ke perangkat ini</div>
            </li>
            <li class="px-3 py-3">
                <div id="pushNotifyStatus" class="small text-muted mb-3" role="status" aria-live="polite">
                    Memeriksa status…
                </div>
                <button type="button" id="pushNotifyToggle"
                        class="btn btn-sm btn-primary w-100 d-none" disabled>
                    Aktifkan Notifikasi
                </button>
                <div class="small text-muted mt-2 d-none" id="pushNotifyHint"></div>
            </li>
            <li class="px-3 pb-2">
                <div class="small text-muted" style="font-size: 0.7rem;">
                    Notifikasi tetap tersimpan di daftar notifikasi aplikasi walau
                    notifikasi perangkat dimatikan.
                </div>
            </li>
        </ul>
    </div>
@endif
