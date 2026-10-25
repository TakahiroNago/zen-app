<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Services\AdminDashboardMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function index(AdminDashboardMetrics $metrics): View
    {
        return view('admin.dashboard', [
            'users' => $metrics->users(),
            'billing' => $metrics->billing(),
            'usage' => $metrics->usage(),
            'errors' => $metrics->errors(),
            'operations' => $metrics->operations(),
        ]);
    }

    public function resolveInquiry(ContactInquiry $inquiry): RedirectResponse
    {
        $inquiry->forceFill(['resolved_at' => $inquiry->resolved_at ?? now()])->save();

        return back()->with('status', __('問い合わせを対応済みにしました。'));
    }
}
