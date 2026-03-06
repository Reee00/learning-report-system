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
    public function run(): void
    {
        // 1. Buat Admin
        User::create([
            'name'     => 'Admin Utama',
            'email'    => 'admin@lrs.com',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);

        // 2. Buat Sekolah
        $school = School::create([
            'name'     => 'SD Harapan Bangsa',
            'address'  => 'Jl. Merdeka No. 10, Jakarta',
            'pic_name' => 'Budi Santoso',
        ]);

        // 3. Buat School PIC
        User::create([
            'name'      => 'Budi Santoso',
            'email'     => 'pic@lrs.com',
            'password'  => bcrypt('password'),
            'role'      => 'school_pic',
            'school_id' => $school->id,
        ]);

        // 4. Buat Coach
        $coach = User::create([
            'name'     => 'Rina Coachella',
            'email'    => 'coach@lrs.com',
            'password' => bcrypt('password'),
            'role'     => 'coach',
        ]);

        // 5. Buat Kelas
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name'      => 'Grade 5A',
        ]);

        // 6. Buat Siswa
        Student::insert([
            ['class_id' => $class->id, 'name' => 'Andi Pratama'],
            ['class_id' => $class->id, 'name' => 'Bela Sari'],
            ['class_id' => $class->id, 'name' => 'Citra Dewi'],
            ['class_id' => $class->id, 'name' => 'Dito Arifin'],
            ['class_id' => $class->id, 'name' => 'Eka Putri'],
        ]);

        // 7. Tugaskan Coach ke Kelas
        CoachClass::create([
            'coach_id' => $coach->id,
            'class_id' => $class->id,
        ]);
    }
}