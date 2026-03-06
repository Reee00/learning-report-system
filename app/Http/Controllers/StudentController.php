<?php
namespace App\Http\Controllers;

use App\Imports\StudentsImport;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    // Halaman detail kelas + daftar siswa
    public function show(SchoolClass $class)
    {
        $this->authorizeAccess($class);

        $class->load('school');
        $students = Student::where('class_id', $class->id)
            ->orderBy('name')
            ->paginate(20);

        return view('students.index', compact('class', 'students'));
    }

    // Tambah siswa manual
    public function store(Request $request, SchoolClass $class)
    {
        $this->authorizeAccess($class);

        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        // Cek duplikat
        $exists = Student::where('class_id', $class->id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Siswa dengan nama tersebut sudah ada di kelas ini.');
        }

        Student::create([
            'class_id' => $class->id,
            'name'     => $request->name,
        ]);

        return back()->with('success', 'Siswa berhasil ditambahkan!');
    }

    // Upload Excel
    public function import(Request $request, SchoolClass $class)
    {
        $this->authorizeAccess($class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            $import = new StudentsImport($class->id);
            Excel::import($import, $request->file('file'));

            return back()->with('success', 'Data siswa berhasil diimport dari Excel!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    // Hapus siswa
    public function destroy(SchoolClass $class, Student $student)
    {
        $this->authorizeAccess($class);

        abort_if($student->class_id !== $class->id, 403);

        $student->delete();
        return back()->with('success', 'Siswa berhasil dihapus.');
    }

    // Download template Excel
    public function template()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_siswa.csv"',
        ];

        $rows = [
            ['nama_siswa'],       // header
            ['Andi Pratama'],     // contoh baris 1
            ['Bela Sari'],        // contoh baris 2
            ['Citra Dewi'],       // contoh baris 3
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Cek hak akses: admin bisa semua, coach hanya kelasnya, pic hanya sekolahnya
    private function authorizeAccess(SchoolClass $class): void
    {
        $user = Auth::user();

        if ($user->role === 'admin') return;

        if ($user->role === 'coach') {
            $assigned = \App\Models\CoachClass::where('coach_id', $user->id)
                ->where('class_id', $class->id)
                ->exists();
            abort_if(!$assigned, 403, 'Kamu tidak memiliki akses ke kelas ini.');
            return;
        }

        if ($user->role === 'school_pic') {
            abort_if($class->school_id !== $user->school_id, 403, 'Kelas ini bukan dari sekolahmu.');
            return;
        }

        abort(403);
    }
}