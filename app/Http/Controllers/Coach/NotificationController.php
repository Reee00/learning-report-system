<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Tandai satu notifikasi milik coach sebagai sudah dibaca.
     * ID UUID divalidasi sebagai milik user yang login sehingga coach
     * tidak bisa menandai notifikasi milik orang lain.
     */
    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }
}
