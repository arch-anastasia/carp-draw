<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingPortalController extends Controller
{
    /**
     * Send the customer to Stripe's hosted billing portal (invoices,
     * payment methods, cancellation).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        try {
            return $request->user()->redirectToBillingPortal(route('billing.index'));
        } catch (\Throwable $e) {
            Log::error('Billing portal unavailable', ['error' => $e->getMessage()]);

            return back()->with('error', 'The billing portal is unavailable right now. Please try again in a minute.');
        }
    }
}
