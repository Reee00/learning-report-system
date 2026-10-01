<?php

namespace Database\Seeders;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\ProgramClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Sumber data tunggal: sheet "🏫 DIGISchool - SENIN" pada
     * "_📅 Sistem Academic - CENTER & DIGISchool.xlsx".
     *
     * Hanya sekolah/kelas/program yang benar-benar terlihat pada sheet itu yang
     * di-seed. Hubungan yang tidak terbaca dari sumber sengaja TIDAK dibuat,
     * supaya data demo tidak menyiratkan penugasan yang tidak pernah ada.
     *
     * Struktur: nama sekolah => center (kolom KEBERANGKATAN) + kelas => program.
     * `classes.school_id` membuat kelas bersifat per-sekolah, jadi label kelas
     * yang sama di dua sekolah tetap dua baris `classes` yang berbeda.
     *
     * AKUN DEMO — HANYA UNTUK PENGEMBANGAN:
     *   Seeder ini membuat akun uji dengan kata sandi bersama `password`.
     *   Kredensialnya sengaja HANYA didokumentasikan di sini (dan di kode
     *   seeder di bawah), bukan lagi di markup halaman login — halaman login
     *   bisa dibuka siapa saja, dan mencantumkan akun beserta kata sandinya di
     *   sana sama dengan mengumumkan kredensial ke publik.
     *
     *     Relation   : admin@lrs.com
     *     SuperAdmin : superadmin@lrs.com
     *     Coach      : coach@lrs.com, coach2@lrs.com
     *     PIC        : pic@lrs.com
     *     Password   : password
     *
     *   JANGAN menjalankan seeder ini di produksi, dan jangan memakai kata
     *   sandi tersebut untuk akun sungguhan.
     */
    private const DIGISCHOOL = [
        'PENABUR MODERNLAND' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 3' => ['Coding'],
            ],
        ],
        'SIS' => [
            'center'  => 'PONDOK INDAH',
            'classes' => [
                'TK'       => ['Coding'],
                'SD 1 - 2' => ['Coding'],
                'SD 3 - 6' => ['Coding'],
            ],
        ],
        'PENABUR GS' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 3' => ['Coding'],
                'SD 4' => ['Coding'],
            ],
        ],
        'LEC' => [
            'center'  => 'BSD',
            'classes' => [
                'Junior 1' => ['DK'],
            ],
        ],
        'GRACIA' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 5-6'    => ['Graphic Design'],
                'SMP + SMA' => ['Graphic Design'],
            ],
        ],
        'EST ALFA INDAH' => [
            'center'  => 'PONDOK INDAH',
            'classes' => [
                'SD 4'       => ['STEM'],
                'SD 1 - SMP' => ['STEM', 'Content Creator'],
            ],
        ],
        'IPEKA BSD' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 3 - 4' => ['Coding'],
            ],
        ],
        'IPEKA PURI' => [
            'center'  => 'PONDOK INDAH',
            'classes' => [
                'TK'     => ['Robot'],
                'SD 1-2' => ['Robot'],
                'SD 3-4' => ['Robot'],
            ],
        ],
        'SANUR' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 3 - 6' => ['Art Factory'],
            ],
        ],
        'IPEKA IICS' => [
            'center'  => 'PONDOK INDAH',
            // Sel kelas di sheet terbaca sebagai tanggal 2025-01-02 karena
            // Excel mengubah "1-2" menjadi date; nilai bisnisnya "1-2".
            'classes' => [
                '1-2' => ['Robotic'],
            ],
        ],
        'BINUS' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 3 - 6' => ['Coding'],
            ],
        ],
        'MUTIARA HARAPAN BINTARO' => [
            'center'  => 'BSD',
            'classes' => [
                'SMP' => ['Coding'],
            ],
        ],
        'PENABUR BINTARO' => [
            'center'  => 'BSD',
            'classes' => [
                'SD 2 D-E' => ['Coding'],
                'SD 2 ABC' => ['Coding'],
            ],
        ],
    ];

    /**
     * Master program. Hanya delapan program yang tampil pada sheet SENIN.
     * "AI" juga terlihat di blok EST ALFA INDAH tetapi tidak termasuk daftar
     * program yang diminta, sehingga sengaja tidak dibuat.
     */
    private const PROGRAMS = [
        'Coding'          => 'CODING',
        'DK'              => 'DK',
        'Graphic Design'  => 'GRAPHIC-DESIGN',
        'STEM'            => 'STEM',
        'Content Creator' => 'CONTENT-CREATOR',
        'Robot'           => 'ROBOT',
        'Art Factory'     => 'ART-FACTORY',
        'Robotic'         => 'ROBOTIC',
    ];

    /**
     * Penugasan coach ke kelas (pivot coach_classes).
     *
     * coach@lrs.com adalah akun coach fungsional utama. coach2@lrs.com adalah
     * satu-satunya akun tambahan, khusus untuk menguji arsitektur multi-coach
     * (dua coach pada kelas yang sama) — bukan nama dari sheet.
     */
    private const COACH_ASSIGNMENTS = [
        'coach@lrs.com' => [
            'name'    => 'Rina Coachella',
            'classes' => [
                ['SIS', 'TK'],
                ['SIS', 'SD 1 - 2'],
                ['SIS', 'SD 3 - 6'],
                ['PENABUR MODERNLAND', 'SD 3'],
                ['PENABUR GS', 'SD 3'],
                ['PENABUR GS', 'SD 4'],
            ],
        ],
        'coach2@lrs.com' => [
            'name'    => 'Coach Pendamping',
            'classes' => [
                ['PENABUR MODERNLAND', 'SD 3'],
            ],
        ],
    ];

    /**
     * Nama coach dari kolom Coach pada sheet "🏫 DIGISCHool - SENIN".
     *
     * Akun-akun ini sengaja dibuat TANPA penugasan sekolah maupun kelas:
     * daftar nama saja tidak membuktikan siapa mengajar kelas mana. Penugasan
     * dibuat lewat UI, bukan disimpulkan dari nama.
     *
     * "Mr Iqbal" adalah koreksi ejaan dari sumber (sempat tertulis dengan
     * huruf L: "Mr lqbal").
     */
    private const COACH_NAMES = [
        'Mr Wildan (GS)',
        'Ms Dhea',
        'Mr Iqbal',
        'Mr Fadli',
        'Mr Ryan',
        'Mr Filipus',
        'Mr Jiha Fajar',
        'Mr Dicky',
        'Mr Alvin (PI)',
        'Mr Denny',
        'Mr Rama',
        'Mr Theo',
        'Mr Fajar',
        'Mr Aji',
        'Ms Laila',
        'Mr Jose',
        'Ms Anindya',
        'Mr Firlan',
        'Mr Morando',
        'Mr Bertrand',
        'Mr Gio',
        'Mr Leo',
        'Ms Ayu',
        'Mr Renaldy',
        'Mr Noddy',
        'Mr Dimas',
        'Mr Charis',
    ];

    /**
     * Nama siswa demo. Jumlah murid sebenarnya ada di kolom JUMLAH MURID
     * sheet (9 s.d. 62 per kelas); roster di sini sengaja diringkas menjadi
     * lima siswa per kelas coach supaya alur absensi dan laporan bisa diuji
     * tanpa mengarang ratusan nama.
     */
    private const STUDENT_POOL = [
        'Aditya Nugraha', 'Bella Kusuma', 'Chandra Wijaya', 'Dinda Maharani',
        'Eka Saputra', 'Farah Amelia', 'Gilang Ramadhan', 'Hana Pertiwi',
        'Irfan Maulana', 'Jasmine Aulia', 'Kevin Halim', 'Laras Ayuningtyas',
    ];

    private const STUDENTS_PER_CLASS = 5;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedAccounts();
            $this->seedCoachAccounts();
            $this->seedSchools();
            $this->seedPrograms();
            $this->seedClassesAndPrograms();
            $this->seedCoachAssignments();
            $this->seedSchoolScopes();
            $this->seedStudents();
        });

        $this->command?->info(sprintf(
            'Seed selesai: %d user, %d sekolah, %d kelas, %d program, %d penugasan coach, %d siswa.',
            User::count(),
            School::count(),
            SchoolClass::count(),
            Program::count(),
            CoachClass::count(),
            Student::count(),
        ));
    }

    /**
     * Satu akun per role. Email adalah kunci natural sehingga seed aman
     * diulang tanpa membuat duplikat.
     */
    private function seedAccounts(): void
    {
        $accounts = [
            'superadmin@lrs.com' => ['SuperAdmin Utama', User::ROLE_SUPERADMIN],
            'admin@lrs.com'      => ['Relation Utama', User::ROLE_RELATION],
            'spv@lrs.com'        => ['Sari Supervisor', User::ROLE_SPV_COACH],
            'coach@lrs.com'      => ['Rina Coachella', User::ROLE_COACH],
            'coach2@lrs.com'     => ['Coach Pendamping', User::ROLE_COACH],
            'pic@lrs.com'        => ['Budi Santoso', User::ROLE_SCHOOL_PIC],
            'teacher@lrs.com'    => ['Dewi Larasati', User::ROLE_TEACHER_SCHOOL],
            'finance@lrs.com'    => ['Fajar Finance', User::ROLE_FINANCE],
        ];

        foreach ($accounts as $email => [$name, $role]) {
            // school_id sengaja null: scope sekolah memakai pivot school_user
            // yang diisi di seedSchoolScopes().
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'      => $name,
                    'password'  => bcrypt('password'),
                    'role'      => $role,
                    'school_id' => null,
                ]
            );
        }
    }

    /**
     * Akun coach dari daftar nama, tanpa penugasan apa pun.
     *
     * `school_id` null dan tidak ada sinkronisasi `schools()` maupun
     * `coach_classes` — tugas ini hanya membuat akunnya.
     */
    private function seedCoachAccounts(): void
    {
        $emails = array_map(fn (string $name): string => $this->coachEmail($name), self::COACH_NAMES);

        // Dua nama yang dinormalisasi ke email yang sama akan saling menimpa
        // diam-diam lewat updateOrCreate. Gagalkan seed daripada kehilangan satu
        // akun tanpa jejak.
        $duplicates = array_keys(array_filter(array_count_values($emails), fn (int $n): bool => $n > 1));

        if ($duplicates !== []) {
            throw new \RuntimeException('Email coach tidak unik: ' . implode(', ', $duplicates));
        }

        foreach (self::COACH_NAMES as $name) {
            User::updateOrCreate(
                ['email' => $this->coachEmail($name)],
                [
                    'name'      => $name,
                    'password'  => bcrypt('password'),
                    'role'      => User::ROLE_COACH,
                    'school_id' => null,
                ]
            );
        }
    }

    /**
     * Email development internal dari nama: huruf kecil, setiap runut karakter
     * non-alfanumerik menjadi satu tanda hubung.
     *
     * "Mr Wildan (GS)" => "mr-wildan-gs@lrs.com"
     */
    private function coachEmail(string $name): string
    {
        $slug = strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-') . '@lrs.com';
    }

    private function seedSchools(): void
    {
        foreach (self::DIGISCHOOL as $name => $data) {
            // pic_name sengaja dibiarkan kosong: kolom Coach pada sheet berisi
            // nama coach, bukan narahubung PIC sekolah, jadi menisinya akan
            // mengarang data.
            School::updateOrCreate(
                ['name' => $name],
                ['address' => 'Center ' . $this->centerLabel($data['center'])]
            );
        }
    }

    private function seedPrograms(): void
    {
        foreach (self::PROGRAMS as $name => $code) {
            Program::updateOrCreate(
                ['name' => $name],
                ['code' => $code, 'status' => 'active']
            );
        }
    }

    /**
     * Kelas bersifat per-sekolah (classes.school_id), lalu program ditautkan
     * lewat pivot program_classes.
     */
    private function seedClassesAndPrograms(): void
    {
        foreach (self::DIGISCHOOL as $schoolName => $data) {
            $school = School::where('name', $schoolName)->firstOrFail();

            foreach ($data['classes'] as $className => $programNames) {
                $class = SchoolClass::updateOrCreate(
                    ['school_id' => $school->id, 'name' => $className]
                );

                foreach ($programNames as $programName) {
                    $program = Program::where('name', $programName)->firstOrFail();

                    ProgramClass::firstOrCreate([
                        'program_id' => $program->id,
                        'class_id'   => $class->id,
                    ]);
                }
            }
        }
    }

    private function seedCoachAssignments(): void
    {
        foreach (self::COACH_ASSIGNMENTS as $email => $data) {
            $coach = User::where('email', $email)->firstOrFail();

            foreach ($data['classes'] as [$schoolName, $className]) {
                CoachClass::firstOrCreate([
                    'coach_id' => $coach->id,
                    'class_id' => $this->classId($schoolName, $className),
                ]);
            }
        }
    }

    /**
     * Scope sekolah memakai pivot school_user (plus kolom legacy school_id).
     *
     * Relation, SuperAdmin, SPV Coach dan Finance bersifat operasional-global
     * lewat role-nya masing-masing (AuthorizationService::accessibleSchoolIds
     * mengembalikan null), jadi keempatnya tidak diberi plot sekolah. Finance
     * sengaja TIDAK di-plot ke semua sekolah — itu akan menutupi aturan
     * autorisasinya, bukan menerapkannya.
     */
    private function seedSchoolScopes(): void
    {
        // PIC: tepat satu sekolah.
        User::where('email', 'pic@lrs.com')->firstOrFail()
            ->schools()->sync([$this->schoolId('PENABUR MODERNLAND')]);

        // Teacher School: tepat satu sekolah.
        User::where('email', 'teacher@lrs.com')->firstOrFail()
            ->schools()->sync([$this->schoolId('PENABUR GS')]);

        // Finance: tidak di-plot. Akses all-school datang dari role, dan
        // satu-satunya pembatasnya adalah status approved
        // (AttendanceScopeService::scopeReports).
        User::where('email', 'finance@lrs.com')->firstOrFail()
            ->schools()->sync([]);
    }

    /**
     * Roster siswa untuk setiap kelas yang punya penugasan coach, supaya
     * alur absensi dan laporan bisa langsung dijalankan.
     */
    private function seedStudents(): void
    {
        $classIds = CoachClass::distinct()->pluck('class_id')->sort()->values();
        $poolSize = count(self::STUDENT_POOL);

        foreach ($classIds as $index => $classId) {
            $offset = ($index * self::STUDENTS_PER_CLASS) % $poolSize;

            for ($i = 0; $i < self::STUDENTS_PER_CLASS; $i++) {
                Student::firstOrCreate([
                    'class_id' => $classId,
                    'name'     => self::STUDENT_POOL[($offset + $i) % $poolSize],
                ]);
            }
        }
    }

    private function schoolId(string $schoolName): int
    {
        return (int) School::where('name', $schoolName)->value('id');
    }

    private function classId(string $schoolName, string $className): int
    {
        $classId = SchoolClass::where('school_id', $this->schoolId($schoolName))
            ->where('name', $className)
            ->value('id');

        if ($classId === null) {
            throw new \RuntimeException("Kelas '{$className}' tidak ditemukan pada sekolah '{$schoolName}'.");
        }

        return (int) $classId;
    }

    private function centerLabel(string $center): string
    {
        return $center === 'BSD' ? 'BSD' : 'Pondok Indah';
    }
}
