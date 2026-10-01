<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolClass extends Model
{
    protected $table = 'classes'; // nama tabel tetap 'classes' di database

    protected $fillable = ['school_id', 'name', 'target_meetings'];

    protected $casts = [
        'target_meetings' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    // reports.class_id memakai FK RESTRICT, jadi relasi ini dipakai untuk
    // memeriksa apakah kelas masih boleh dihapus.
    public function reports()
    {
        return $this->hasMany(Report::class, 'class_id');
    }

    public function coachAssignments()
    {
        return $this->hasMany(CoachClass::class, 'class_id');
    }

    /**
     * Jadwal mengajar kelas ini. Dipakai School Workspace untuk memastikan
     * kelas ber-jadwal tidak dipindah/dihapus sembarangan (school_id pada
     * jadwal historis tidak boleh bertentangan dengan school_id kelas).
     */
    public function teachingSchedules()
    {
        return $this->hasMany(TeachingSchedule::class, 'class_id');
    }

    public function programClasses()
    {
        return $this->hasMany(ProgramClass::class, 'class_id');
    }

    public function programs()
    {
        return $this->belongsToMany(
            Program::class,
            'program_classes',
            'class_id',
            'program_id'
        )->withTimestamps();
    }

    /**
     * Kelas yang boleh dilaporkan seorang coach: penugasan permanen
     * (`coach_classes`) ATAU penugasan sementara lewat sesi mengajar
     * (`teaching_schedules`, sebagai coach utama maupun tambahan).
     *
     * Dipakai dropdown "buat/edit laporan" supaya coach tambahan yang belum
     * punya `coach_classes` tetap bisa memilih kelas yang benar-benar dia ajar.
     *
     * `$activeOnly` dipakai form PEMBUATAN laporan: sesi nonaktif tidak
     * menerima laporan baru (aturan final 2026-09-28), jadi kelas yang hanya
     * terjangkau lewat sesi nonaktif tidak perlu ditawarkan. Form edit tetap
     * memakai daftar penuh supaya laporan yang sudah ada — termasuk yang
     * sesinya dinonaktifkan belakangan — tetap bisa dikoreksi.
     */
    public function scopeReportableBy($query, int $coachId, bool $activeOnly = false)
    {
        return $query->where(function ($q) use ($coachId, $activeOnly): void {
            $q->whereHas('coachAssignments', fn ($c) => $c->where('coach_id', $coachId))
                ->orWhereHas('teachingSchedules', function ($s) use ($coachId, $activeOnly): void {
                    $s->forCoach($coachId);

                    if ($activeOnly) {
                        $s->active();
                    }
                });
        });
    }

    // ------------------------------------------------------------------
    // Target & progress pertemuan (penjadwalan berbasis sesi, 2026-09-29)
    // ------------------------------------------------------------------

    /**
     * Target jumlah pertemuan kelas ini.
     *
     * Sumbernya berurutan: kolom `target_meetings` bila sudah ditetapkan,
     * lalu jumlah `meeting_count` pola yang masih terpasang, lalu jumlah sesi
     * yang sudah ada. NULL berarti target memang belum ditetapkan — bukan 0 —
     * supaya UI bisa membedakan "target 0" dari "target belum diisi".
     */
    public function sessionTarget(): ?int
    {
        if ($this->target_meetings !== null) {
            return (int) $this->target_meetings;
        }

        return self::sessionTargetsFor([$this->id])[$this->id] ?? null;
    }

    /**
     * Target + progress untuk sekumpulan kelas dalam SATU query — dipakai
     * daftar sesi supaya tidak menembak database per baris.
     *
     * `terlaksana` adalah satu-satunya angka progress yang dipakai UI: jumlah
     * PERTEMUAN (sesi) yang sudah punya laporan dengan status yang dianggap
     * selesai oleh lifecycle LRS (`Report::COMPLETED_STATUSES`). Yang dihitung
     * adalah sesinya, bukan laporannya, sehingga satu sesi tidak pernah
     * terhitung dua kali walau laporannya di-reject lalu dikirim ulang.
     *
     * `scheduled`/`unscheduled` tetap disediakan sebagai konteks rencana
     * (berapa pertemuan yang sudah punya tanggal), bukan sebagai progress.
     *
     * @param  iterable<int, int|string>  $classIds
     * @return Collection<int, array{target: ?int, terlaksana: int, scheduled: int, unscheduled: int, completed: int}>
     */
    public static function sessionProgressFor(iterable $classIds): Collection
    {
        $ids = collect($classIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        $targets = self::sessionTargetsFor($ids);

        $counts = DB::table('teaching_schedules')
            ->whereIn('class_id', $ids)
            ->where('status', '!=', TeachingSchedule::STATUS_CANCELLED)
            ->groupBy('class_id')
            ->selectRaw('class_id')
            ->selectRaw('SUM(CASE WHEN session_date IS NOT NULL THEN 1 ELSE 0 END) as scheduled')
            ->selectRaw('SUM(CASE WHEN session_date IS NULL THEN 1 ELSE 0 END) as unscheduled')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed", [TeachingSchedule::STATUS_COMPLETED])
            ->get()
            ->keyBy('class_id');

        $terlaksana = self::completedSessionsFor($ids);

        return collect($ids)->mapWithKeys(function (int $id) use ($targets, $counts, $terlaksana): array {
            $row = $counts->get($id);

            return [$id => [
                'target'      => $targets[$id] ?? null,
                'terlaksana'  => (int) ($terlaksana[$id] ?? 0),
                'scheduled'   => (int) ($row->scheduled ?? 0),
                'unscheduled' => (int) ($row->unscheduled ?? 0),
                'completed'   => (int) ($row->completed ?? 0),
            ]];
        });
    }

    /**
     * Jumlah PERTEMUAN TERLAKSANA per kelas: sesi yang punya laporan berstatus
     * `submitted`/`approved`.
     *
     * Diambil dari sisi `teaching_schedules` (bukan dari sisi laporan) supaya
     * COUNT-nya pasti per sesi — satu sesi maksimum dihitung sekali, dan sesi
     * yang dibatalkan tidak pernah ikut terhitung. Pencocokan laporan memakai
     * aturan yang sama dengan reminder: tautan langsung `teaching_schedule_id`,
     * dengan cadangan kelas + tanggal untuk laporan lama tanpa tautan sesi.
     *
     * @param  array<int, int>  $classIds
     * @return array<int, int>
     */
    private static function completedSessionsFor(array $classIds): array
    {
        if ($classIds === []) {
            return [];
        }

        return DB::table('teaching_schedules')
            ->whereIn('teaching_schedules.class_id', $classIds)
            ->where('teaching_schedules.status', '!=', TeachingSchedule::STATUS_CANCELLED)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('reports')
                    ->where(function ($match): void {
                        $match->whereColumn('reports.teaching_schedule_id', 'teaching_schedules.id')
                            ->orWhere(function ($legacy): void {
                                $legacy->whereNull('reports.teaching_schedule_id')
                                    ->whereColumn('reports.class_id', 'teaching_schedules.class_id')
                                    ->whereColumn('reports.report_date', 'teaching_schedules.session_date');
                            });
                    })
                    ->whereIn('reports.status', Report::COMPLETED_STATUSES);
            })
            ->groupBy('teaching_schedules.class_id')
            ->selectRaw('teaching_schedules.class_id as class_id, COUNT(*) as total')
            ->pluck('total', 'class_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Target per kelas dari kolom `target_meetings`, atau turunannya dari pola
     * lalu dari jumlah sesi. Dipakai bersama oleh sessionTarget() dan
     * sessionProgressFor() agar keduanya tidak pernah berbeda angka.
     *
     * @param  array<int, int>  $classIds
     * @return array<int, int>
     */
    private static function sessionTargetsFor(array $classIds): array
    {
        if ($classIds === []) {
            return [];
        }

        $explicit = DB::table('classes')
            ->whereIn('id', $classIds)
            ->whereNotNull('target_meetings')
            ->pluck('target_meetings', 'id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $missing = array_values(array_diff($classIds, array_keys($explicit)));

        if ($missing === []) {
            return $explicit;
        }

        $derived = [];

        // Pola masih terpasang = sumber target paling sah untuk kelas ini.
        $fromPatterns = DB::table('teaching_schedule_templates')
            ->whereIn('class_id', $missing)
            ->groupBy('class_id')
            ->selectRaw('class_id, SUM(meeting_count) as total')
            ->pluck('total', 'class_id');

        foreach ($fromPatterns as $classId => $total) {
            if ((int) $total > 0) {
                $derived[(int) $classId] = (int) $total;
            }
        }

        $stillMissing = array_values(array_diff($missing, array_keys($derived)));

        if ($stillMissing !== []) {
            $fromSessions = DB::table('teaching_schedules')
                ->whereIn('class_id', $stillMissing)
                ->where('status', '!=', TeachingSchedule::STATUS_CANCELLED)
                ->groupBy('class_id')
                ->selectRaw('class_id, COUNT(*) as total')
                ->pluck('total', 'class_id');

            foreach ($fromSessions as $classId => $total) {
                if ((int) $total > 0) {
                    $derived[(int) $classId] = (int) $total;
                }
            }
        }

        return $explicit + $derived;
    }
}
