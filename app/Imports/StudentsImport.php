<?php
namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class StudentsImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    private int $classId;

    public function __construct(int $classId)
    {
        $this->classId = $classId;
    }

    public function model(array $row): ?Student
    {
        // Kolom di Excel harus bernama "nama_siswa" (header row)
        $name = trim($row['nama_siswa'] ?? $row['name'] ?? '');

        if (empty($name)) return null;

        // Cek duplikat, jangan insert jika sudah ada
        $exists = Student::where('class_id', $this->classId)
            ->where('name', $name)
            ->exists();

        if ($exists) return null;

        return new Student([
            'class_id' => $this->classId,
            'name'     => $name,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_siswa' => 'sometimes|string',
            'name'       => 'sometimes|string',
        ];
    }
}