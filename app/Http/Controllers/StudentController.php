<?php
namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Rap2hpoutre\FastExcel\FastExcel;

class StudentController extends Controller
{
    // Halaman detail kelas + daftar siswa
    public function show(SchoolClass $class)
    {
        $this->authorizeAccess($class, 'students.view');

        $class->load('school');
        $students = Student::where('class_id', $class->id)
            ->orderBy('name')
            ->paginate(20);

        return view('students.index', compact('class', 'students'));
    }

    // Tambah siswa manual
    public function store(Request $request, SchoolClass $class)
    {
        $this->authorizeAccess($class, 'students.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $exists = Student::where('class_id', $class->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Siswa dengan nama tersebut sudah ada di kelas ini.');
        }

        Student::create([
            'class_id' => $class->id,
            'name'     => $validated['name'],
        ]);

        return back()->with('success', 'Siswa berhasil ditambahkan!');
    }

    // Upload Excel
    public function import(Request $request, SchoolClass $class)
    {
        $this->authorizeAccess($class, 'students.create');

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ]);

        try {
            $file = $request->file('file');
            $imported = 0;
            $skipped  = 0;

            (new FastExcel)->import($file->getPathname(), function ($row) use ($class, &$imported, &$skipped) {
                $name = trim($row['nama_siswa'] ?? $row['name'] ?? $row['Nama Siswa'] ?? '');

                if (empty($name)) return null;

                $exists = Student::where('class_id', $class->id)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    return null;
                }

                Student::create([
                    'class_id' => $class->id,
                    'name'     => $name,
                ]);

                $imported++;
            });

            $message = "{$imported} siswa berhasil diimport.";
            if ($skipped > 0) {
                $message .= " {$skipped} siswa dilewati karena sudah ada.";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    // Hapus siswa
    public function destroy(SchoolClass $class, Student $student)
    {
        $this->authorizeAccess($class, 'students.delete');

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
            ['nama_siswa'],
            ['Andi Pratama'],
            ['Bela Sari'],
            ['Citra Dewi'],
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

    // Cek hak akses
    private function authorizeAccess(SchoolClass $class, string $permission): void
    {
        $user = Auth::user();
        $authorization = app(AuthorizationService::class);

        abort_unless(
            $authorization->allows($user, $permission),
            403,
            'Permission tidak mencukupi.'
        );

        abort_unless(
            $authorization->canAccessClass($user, $class),
            403,
            'Kamu tidak memiliki akses ke kelas ini.'
        );

        abort_unless(
            $class->school()->exists(),
            422,
            'Kelas belum terhubung ke sekolah.'
        );
    }
}
