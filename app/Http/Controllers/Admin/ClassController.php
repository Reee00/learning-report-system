<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::with('school')->paginate(20);
        $schools = School::orderBy('name')->get();
        return view('admin.master.classes', compact('classes', 'schools'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'school_id' => 'required|exists:schools,id',
            'name'      => 'required|string|max:100',
        ]);

        SchoolClass::create($request->only('school_id', 'name'));
        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function destroy(SchoolClass $class)
    {
        $class->delete();
        return back()->with('success', 'Kelas berhasil dihapus.');
    }
}