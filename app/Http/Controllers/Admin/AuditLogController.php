<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public const GROUPS = [
        'auth' => 'Sign-in and passwords',
        'user' => 'Accounts and roles',
        'chapter' => 'Chapters',
        'event' => 'Events',
        'registration' => 'Registrations',
        'material' => 'Materials',
        'report' => 'Reports',
    ];

    public function index(Request $request): View
    {
        $group = array_key_exists((string) $request->input('group'), self::GROUPS) ? $request->input('group') : null;

        $logs = AuditLog::with('user')
            ->when($group, fn ($q) => $q->where('action', 'like', $group.'.%'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->input('q').'%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log', ['logs' => $logs, 'groups' => self::GROUPS, 'group' => $group]);
    }
}
