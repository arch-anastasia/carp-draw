<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\ExceptionInterface;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * The plans page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('App/Plans', [
            'plans' => $this->subscriptions->getPlansForUser($user),
            'subscription' => $this->subscriptions->subscriptionState($user),
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $planKey = $request->validated()['plan'];
        $currentKey = $this->subscriptions->getCurrentPlanKey($user);

        // Re-choosing the current plan is a no-op, never a second charge.
        if ($currentKey === $planKey) {
            return back()->with('info', 'You are already on this plan.');
        }

        try {
            if ($currentKey !== null) {
                // Choosing the other plan is a swap, never a second
                // concurrent subscription.
                $this->subscriptions->swap($user, $planKey);

                $planName = config("stripe.plans.{$planKey}.name", $planKey);

                return redirect()->route('billing.index')
                    ->with('success', "You're now on the {$planName} plan.");
            }

            $result = $this->subscriptions->subscribe(
                $user,
                $planKey,
                $request->input('interval', 'monthly'),
            );
        } catch (IncompletePayment $e) {
            // 3D Secure: hand over to Cashier's payment page, full-page.
            return Inertia::location(route('cashier.payment', [
                $e->payment->asStripePaymentIntent()->id,
                'redirect' => route('billing.success'),
            ]));
        } catch (ExceptionInterface|\InvalidArgumentException|\RuntimeException $e) {
            /*
             * A payment-provider failure (misconfigured key, Stripe outage,
             * a swap with no saved payment method) must never surface as a
             * raw exception page on the pricing screen — that is the single
             * worst place to crash.
             */
            Log::error('Subscribe failed at the payment provider', [
                'plan' => $planKey,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'We could not start the checkout just now. Nothing was charged — please try again in a minute.');
        }

        if ($result->requiresCheckout()) {
            return Inertia::location($result->checkoutUrl);
        }

        return redirect($result->successUrl);
    }

    public function success(): RedirectResponse
    {
        // The webhook that records the subscription may still be in flight —
        // promise activation, not credits that might not be there yet.
        return redirect()->route('workspace')
            ->with('success', 'Payment received — your subscription is activating. Your credits will appear in a moment.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->subscriptions->cancel($request->user());
        } catch (ExceptionInterface|\InvalidArgumentException $e) {
            Log::error('Cancel failed at the payment provider', ['error' => $e->getMessage()]);

            return back()->with('error', 'We could not reach the payment provider to cancel. Your plan is unchanged — please try again, or contact support.');
        }

        return back()->with('success', 'Subscription cancelled. You have access until the end of your billing period.');
    }

    public function resume(Request $request): RedirectResponse
    {
        try {
            $this->subscriptions->resume($request->user());
        } catch (ExceptionInterface|\InvalidArgumentException $e) {
            Log::error('Resume failed at the payment provider', ['error' => $e->getMessage()]);

            return back()->with('error', 'We could not reach the payment provider to resume. Please try again in a minute.');
        }

        return back()->with('success', 'Subscription resumed.');
    }
}
