<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:activity.view')];
    }

    public function index(Request $request): View
    {
        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($request->filled('log'), fn ($q) => $q->where('log_name', $request->input('log')))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $logNames = Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values();

        return view('admin.activity.index', compact('activities', 'logNames'));
    }

    public function logins(Request $request): View
    {
        $logins = LoginHistory::query()
            ->with('authenticatable')
            ->when($request->filled('q'), fn ($q) => $q->where('email', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('guard'), fn ($q) => $q->where('guard', $request->input('guard')))
            ->when($request->filled('status'), fn ($q) => $q->where('successful', $request->input('status') === 'success'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.activity.logins', compact('logins'));
    }
}
