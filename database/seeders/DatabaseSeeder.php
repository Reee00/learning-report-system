<?php
namespace Database\Seeders;

use App\Models\CoachClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent demo data covering every canonical role, so the full
     * SuperAdmin -> School -> PIC -> Coach -> Attendance -> Finance journey can
     * be walked without re-creating duplicate schools, classes or students.
     */
    public function run(): void
    {
        // 1. Existing demo admin is now the operational Relation account.
        User::updateOrCreate(
            ['email' => 'admin@lrs.com'],
            [
                'name'     => 'Relation Utama',
                'password' => bcrypt('password'),
                'role'     => User::ROLE_RELATION,
                'school_id' => null,
            ]
        );

        // 2. SuperAdmin is a separate full-access role.
        User::updateOrCreate(
            ['email' => 'superadmin@lrs.com'],
            [
                'name'     => 'SuperAdmin Utama',
                'password' => bcrypt('password'),
                'role'     => User::ROLE_SUPERADMIN,
                'school_id' => null,
            ]
        );

        // 3. Buat Sekolah
        $school = School::updateOrCreate(
            ['name' => 'SD Harapan Bangsa'],
            [
                'address'  => 'Jl. Merdeka No. 10, Jakarta',
                'pic_name' => 'Budi Santoso',
            ]
        );

        // 4. Buat School PIC; scope sekolah memakai pivot school_user.
        $pic = User::updateOrCreate(
            ['email' => 'pic@lrs.com'],
            [
                'name'      => 'Budi Santoso',
                'password'  => bcrypt('password'),
                'role'      => User::ROLE_SCHOOL_PIC,
                'school_id' => $school->id,
            ]
        );
        $pic->schools()->sync([$school->id]);

        // 5. Buat Coach
        $coach = User::updateOrCreate(
            ['email' => 'coach@lrs.com'],
            [
                'name'     => 'Rina Coachella',
                'password' => bcrypt('password'),
                'role'     => User::ROLE_COACH,
                'school_id' => null,
            ]
        );

        // 6. Buat SPV Coach (mengelola dan assign Coach).
        User::updateOrCreate(
            ['email' => 'spv@lrs.com'],
            [
                'name'     => 'Sari Supervisor',
                'password' => bcrypt('password'),
                'role'     => User::ROLE_SPV_COACH,
                'school_id' => null,
            ]
        );

        // 7. Buat Finance; scope sekolah wajib ada agar export CSV tidak kosong.
        $finance = User::updateOrCreate(
            ['email' => 'finance@lrs.com'],
            [
                'name'      => 'Fajar Finance',
                'password'  => bcrypt('password'),
                'role'      => User::ROLE_FINANCE,
                'school_id' => $school->id,
            ]
        );
        $finance->schools()->sync([$school->id]);

        // 8. Buat Kelas
        $class = SchoolClass::updateOrCreate([
            'school_id' => $school->id,
            'name'      => 'Grade 5A',
        ]);

        // 9. Buat Siswa
        foreach (['Andi Pratama', 'Bela Sari', 'Citra Dewi', 'Dito Arifin', 'Eka Putri'] as $studentName) {
            Student::firstOrCreate([
                'class_id' => $class->id,
                'name'     => $studentName,
            ]);
        }

        // 10. Tugaskan Coach ke Kelas
        CoachClass::firstOrCreate([
            'coach_id' => $coach->id,
            'class_id' => $class->id,
        ]);
    }
}
