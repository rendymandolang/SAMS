<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyContextController extends Controller
{
    public function update(Request $request, CompanyContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $context->switchTo((int) $validated['company_id']);

        return redirect()
            ->route('dashboard')
            ->with('status', __('common.feedback.company_switched'));
    }
}
