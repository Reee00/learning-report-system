<?php

namespace App\Services;

use App\Models\TeachingSchedule;
use App\Models\User;
use App\Notifications\CustomNotification;
use Illuminate\Support\Collection;

/**
 * Pengiriman notifikasi custom ke coach (audit UX 2026-09-11).
 *
 * Scope targeting:
 * - Relation/SuperAdmin: semua coach (scope operasional global).
 * - PIC School: hanya coach yang mengajar (utama maupun tambahan) di
 *   sekolah plot-nya — aturan yang sama dengan ReportReminderService,
 *   tidak boleh cross-school.
 *
 * Target yang di luar scope otomatis ditolak (bukan silently dropped)
 * supaya pengirim tahu pilihannya tidak valid.
 */
class CustomNotificationService
{
    /**
     * Coach yang boleh dituju oleh pengirim.
     *
     * @return Collection<int, User>
     */
    public function targetableCoaches(User $sender): Collection
    {
        $scope = $this->schoolScope($sender);

        return User::where('role', User::ROLE_COACH)
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->whereHas('coachClasses.schoolClass', fn ($c) => $c->whereIn('school_id', $scope))
                    ->orWhereHas('additionalSchedules', fn ($s) => $s->whereIn('school_id', $scope));
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Kirim notifikasi ke daftar coach. Mengembalikan jumlah terkirim;
     * melempar ValidationException bila ada target di luar scope.
     *
     * @param  Collection<int, User>|array<int, int>  $coaches
     */
    public function send(User $sender, $coachIds, string $title, string $message, string $type, ?string $actionUrl = null): int
    {
        $coachIds = collect($coachIds)->map(fn ($id) => (int) $id)->unique()->values();

        $allowed = $this->targetableCoaches($sender)->pluck('id')->all();

        $outOfScope = $coachIds->diff($allowed);
        if ($outOfScope->isNotEmpty()) {
            $names = User::whereIn('id', $outOfScope)->pluck('name')->implode(', ');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'coach_ids' => "Coach {$names} berada di luar scope Anda dan tidak dapat dituju.",
            ]);
        }

        $sent = 0;
        User::where('role', User::ROLE_COACH)
            ->whereIn('id', $coachIds)
            ->get()
            ->each(function (User $coach) use ($sender, $title, $message, $type, $actionUrl, &$sent): void {
                $coach->notify(new CustomNotification(
                    senderName: $sender->name,
                    senderRole: $sender->roleLabel(),
                    title: $title,
                    message: $message,
                    actionUrl: $actionUrl,
                    type: $type,
                ));
                $sent++;
            });

        return $sent;
    }

    /**
     * Null = scope global (Relation/SuperAdmin). Array = scope sekolah PIC.
     *
     * @return array<int, int>|null
     */
    private function schoolScope(User $sender): ?array
    {
        if ($sender->isSuperAdmin() || $sender->isRelationUser()) {
            return null;
        }

        if ($sender->role === User::ROLE_SCHOOL_PIC) {
            return $sender->assignedSchoolIds();
        }

        return []; // role lain tidak boleh mengirim
    }
}
