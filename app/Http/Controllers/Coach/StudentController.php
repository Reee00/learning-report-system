<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    /**
     * Tampilkan daftar kelas yang di-assign ke coach yang sedang login.
     * Dari sini coach dapat mengklik kelas untuk masuk ke halaman siswa.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        $query = SchoolClass::whereHas('coachAssignments', function ($q) {
            $q->where('coach_id', Auth::id());
        })->with('school');

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('school', function ($q2) use ($search) {
                      $q2->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $classes = $query->orderBy('name')->get();

        return view('coach.students.index', compact('classes', 'search'));
    }
}
