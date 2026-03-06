<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachClass;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    // Daftar semua coach
    public function index()
    {
        $coaches = User::where('role', 'coach')
            ->with(['coachClasses.schoolClass.school'])
            ->paginate(15);

        return view('admin.master.coaches', compact('coaches'));
    }

    // Detail coach: lihat & kelola assignment kelas
    public function show(User $coach)
    {
        abort_if($coach->role !== 'coach', 404);

        $coach->load('coachClasses.schoolClass.school');

        // Semua kelas yang BELUM di-assign ke coach ini
        $assignedClassIds = $coach->coachClasses->pluck('class_id')->toArray();
        $availableClasses = SchoolClass::with('school')
            ->whereNotIn('id', $assignedClassIds)
            ->orderBy('name')
            ->get()
            ->groupBy('school.name'); // group berdasarkan sekolah

        return view('admin.master.coach_show', compact('coach', 'availableClasses'));
    }

    // Assign coach ke kelas
    public function assign(Request $request, User $coach)
    {
        abort_if($coach->role !== 'coach', 404);

        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);

        // Cek apakah sudah di-assign sebelumnya
        $already = CoachClass::where('coach_id', $coach->id)
            ->where('class_id', $request->class_id)
            ->exists();

        if ($already) {
            return back()->with('error', 'Coach sudah di-assign ke kelas ini.');
        }

        CoachClass::create([
            'coach_id' => $coach->id,
            'class_id' => $request->class_id,
        ]);

        return back()->with('success', 'Kelas berhasil di-assign ke coach!');
    }

    // Hapus assignment
    public function unassign(User $coach, CoachClass $assignment)
    {
        abort_if($assignment->coach_id !== $coach->id, 403);

        $assignment->delete();

        return back()->with('success', 'Assignment berhasil dihapus.');
    }
}