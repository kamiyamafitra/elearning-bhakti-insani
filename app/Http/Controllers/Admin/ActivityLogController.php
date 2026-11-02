<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(array_keys(ActivityLog::ACTION_LABELS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $action = $validated['action'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $logs = ActivityLog::with('user:id,name,role')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($action, fn ($query, string $action) => $query->where('action', $action))
            ->when($from, fn ($query, string $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => compact('search', 'action', 'from', 'to'),
            'actionLabels' => ActivityLog::ACTION_LABELS,
            'todayCount' => ActivityLog::where('created_at', '>=', today())->count(),
            'failedLoginsToday' => ActivityLog::where('action', ActivityLog::ACTION_LOGIN_FAILED)->where('created_at', '>=', today())->count(),
        ]);
    }
}
