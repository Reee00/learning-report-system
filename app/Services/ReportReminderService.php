<?php

namespace App\Services;

use App\Models\Report;
use App\Models\TeachingSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use App\Notifications\ReportReminderNotification;

/**
 * Reminder laporan (meeting 2026-09 requirement D).
 *
 * Aturan "belum selesai" mengikuti aturan final 2026-09-28: SATU SESI = SATU
 * LAPORAN. Sebuah sesi mengajar (teaching_schedules) pada hari ini atau
 * sebelumnya dianggap selesai begitu ADA satu laporan untuk sesi itu — siapa
 * pun pembuatnya, coach utama maupun coach pendamping. Rujukan utamanya
 * `reports.teaching_schedule_id`.
 *
 * Sebelumnya kecocokan dihitung per COACH (coach_id + class_id + report_date),
 * sehingga sesi bersama dua coach bisa dihitung menunggak untuk coach kedua
 * walau laporannya sudah ada. Kecocokan lama tetap dipakai sebagai cadangan
 * HANYA untuk laporan yang belum punya tautan sesi (data sebelum migrasi),
 * dan sekarang pun tidak lagi mensyaratkan coach_id yang sama.
 *
 * Scope:
 * - Relation / SuperAdmin: semua coach yang berlaku.
 * - PIC School: hanya coach yang mengajar di kelas pada sekolah plot-nya.
 */
class ReportReminderService
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    /**
     * Sesi mengajar terlewat (tanggal <= hari ini) tanpa laporan untuk sesi itu.
     *
     * Sesi nonaktif (is_active = false) TIDAK dihitung: sekolah menandainya
     * karena libur/ujian, jadi tidak ada kewajiban laporan untuk sesi itu.
     * Sesi tetap tersimpan dan kembali berlaku begitu diaktifkan lagi.
     *
     * @return Collection<int, TeachingSchedule>
     */
    public function missingSessions(User $sender): Collection
    {
        $query = TeachingSchedule::query()
            ->active()
            ->with(['school', 'schoolClass'])
            ->whereDate('session_date', '<=', today()->toDateString())
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('reports')
                    ->where(function ($match): void {
                        // Jalur utama: laporan menunjuk langsung ke sesi ini.
                        $match->whereColumn('reports.teaching_schedule_id', 'teaching_schedules.id')
                            // Cadangan untuk laporan lama tanpa tautan sesi:
                            // kelas + tanggal yang sama sudah cukup menandai sesi
                            // ini selesai, tanpa melihat coach pembuatnya.
                            ->orWhere(function ($legacy): void {
                                $legacy->whereNull('reports.teaching_schedule_id')
                                    ->whereColumn('reports.class_id', 'teaching_schedules.class_id')
                                    ->whereColumn('reports.report_date', 'teaching_schedules.session_date');
                            });
                    })
                    ->whereIn('reports.status', Report::COMPLETED_STATUSES);
            });

        $picScope = $this->picScope($sender);
        if ($picScope === null) {
            // Relation / SuperAdmin: scope global, tidak ada filter tambahan.
        } elseif ($picScope === []) {
            // Sender tanpa hak remind: tidak ada sesi yang berlaku.
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('teaching_schedules.school_id', $picScope);
        }

        return $query->orderBy('session_date')->get();
    }

    /**
     * Coach yang memiliki sesi belum dilaporkan, beserta jumlah sesinya.
     *
     * @return Collection<int, User> dengan atribut tambahan missing_sessions_count.
     */
    public function overdueCoaches(User $sender): Collection
    {
        $sessions = $this->missingSessions($sender);

        $counts = $sessions->groupBy('coach_id');

        return User::query()
            ->where('role', User::ROLE_COACH)
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get()
            ->each(fn (User $coach) => $coach->missing_sessions_count = $counts[$coach->id]->count());
    }

    /**
     * Kirim reminder ke satu coach atau beberapa coach sekaligus.
     *
     * Deduplikasi (aturan bisnis 2026-09-11): coach yang sudah memiliki
     * reminder BELUM DIBACA tidak dikirimi reminder baru, tidak peduli siapa
     * pengirimnya (Relation maupun PIC). Setelah reminder lama dibaca,
     * reminder baru boleh dibuat lagi.
     *
     * @param  Collection<int, User>|User  $coaches
     * @return int Jumlah penerima yang benar-benar dinotifikasi.
     */
    public function send(User $sender, $coaches, ?string $message = null): int
    {
        $coaches = $coaches instanceof User ? collect([$coaches]) : collect($coaches);

        $sessionsByCoach = $this->missingSessions($sender)->groupBy('coach_id');

        $targets = $coaches
            ->filter(fn ($coach) => $coach instanceof User)
            ->filter(fn (User $coach) => $coach->role === User::ROLE_COACH
                && $this->senderCanRemindCoach($sender, $coach))
            ->values();

        $sent = 0;

        foreach ($targets as $coach) {
            // Dedup per coach: tahan pengiriman selama masih ada reminder
            // belum dibaca dari pengirim mana pun.
            $hasUnreadReminder = $coach->unreadNotifications()
                ->where('type', ReportReminderNotification::class)
                ->exists();

            if ($hasUnreadReminder) {
                continue;
            }

            $missingCount = $sessionsByCoach->get($coach->id)?->count() ?? 0;

            $coach->notify(
                new ReportReminderNotification(
                    senderName: $sender->name,
                    senderRole: $sender->roleLabel(),
                    missingCount: $missingCount,
                    message: $message,
                )
            );

            $sent++;
        }

        return $sent;
    }

    /**
     * Boundary pencegahan akses lintas sekolah: PIC hanya boleh mengingatkan
     * coach yang mengajar di sekolah plot-nya.
     */
    public function senderCanRemindCoach(User $sender, User $coach): bool
    {
        $picScope = $this->picScope($sender);

        if ($picScope === null) {
            // Relation / SuperAdmin: semua coach berlaku.
            return true;
        }

        return TeachingSchedule::query()
            ->active()
            ->whereIn('school_id', $picScope)
            ->forCoach($coach->id)
            ->exists();
    }

    /**
     * Null = scope global (Relation/SuperAdmin). Array = scope sekolah PIC.
     *
     * @return array<int, int>|null
     */
    private function picScope(User $sender): ?array
    {
        if ($sender->isSuperAdmin() || $sender->isRelationUser()) {
            return null;
        }

        if ($sender->role === User::ROLE_SCHOOL_PIC) {
            return $sender->assignedSchoolIds();
        }

        return [];
    }
}
