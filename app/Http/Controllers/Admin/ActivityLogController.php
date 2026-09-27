<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Konsol activity log (audit keamanan 2026-09-11) — SuperAdmin only.
 * Route diproteksi permission users.manage (hanya SuperAdmin); controller
 * tetap menegaskan role superadmin sebagai lapisan kedua.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($user->isSuperAdmin(), 403, 'Activity log hanya dapat dilihat SuperAdmin.');

        $query = ActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('role')) {
            $query->where('user_role', $request->role);
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', $request->action . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name', 'role']);
        $actions = ActivityLog::query()
            ->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.activity-logs.index', compact('logs', 'users', 'actions'));
    }

    public function show(ActivityLog $log)
    {
        $user = $this->actingUser();
        abort_unless($user->isSuperAdmin(), 403, 'Activity log hanya dapat dilihat SuperAdmin.');

        $log->load('user');

        return view('admin.activity-logs.show', compact('log'));
    }

    private function actingUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
