<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index()
    {
        $schools = School::withCount('classes')->paginate(15);
        return view('admin.master.schools', compact('schools'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:150',
            'address'  => 'nullable|string',
            'pic_name' => 'nullable|string|max:100',
        ]);

        School::create($request->only('name', 'address', 'pic_name'));
        return back()->with('success', 'Sekolah berhasil ditambahkan.');
    }

    public function update(Request $request, School $school)
    {
        $request->validate([
            'name'     => 'required|string|max:150',
            'address'  => 'nullable|string',
            'pic_name' => 'nullable|string|max:100',
        ]);

        $school->update($request->only('name', 'address', 'pic_name'));
        return back()->with('success', 'Data sekolah berhasil diperbarui.');
    }

    public function destroy(School $school)
    {
        $school->delete();
        return back()->with('success', 'Sekolah berhasil dihapus.');
    }
}