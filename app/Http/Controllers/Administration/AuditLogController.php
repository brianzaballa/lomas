<?php

namespace App\Http\Controllers\Administration;

use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Read only view of the audit trail.
     */
    public function index(): Response
    {
        $this->authorize(Permission::AUDIT_VIEW);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when(request('action'), fn (Builder $query, string $action) => $query->where('action', 'like', "{$action}%"))
            ->when(request('user'), fn (Builder $query, string $user) => $query->whereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$user}%")))
            ->when(request('from'), fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when(request('to'), fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->name,
                'record' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('administration/AuditLogs/Index', [
            'logs' => $logs,
            'actions' => AuditAction::all(),
            'filters' => [
                'action' => request('action'),
                'user' => request('user'),
                'from' => request('from'),
                'to' => request('to'),
            ],
        ]);
    }
}
