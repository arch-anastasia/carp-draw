<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Notifications\ContactMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * The contact form's real submit. JSON — the Blade form is enhanced by
     * marketing.ts with a fetch, and validation messages mirror the
     * client-side copy exactly.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'in:product,studio,integration,other'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ], [
            'name.required' => 'Enter your name.',
            'name.min' => 'Enter your name.',
            'email.required' => 'Enter a valid email address.',
            'email.email' => 'Enter a valid email address.',
            'subject.required' => 'Choose a topic.',
            'subject.in' => 'Choose a topic.',
            'message.required' => 'Tell us a little more (at least 20 characters).',
            'message.min' => 'Tell us a little more (at least 20 characters).',
        ]);

        $adminEmail = config('branding.admin_email');

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new ContactMessageNotification($validated));
        }

        // The enhanced form fetches with Accept: application/json; a plain
        // no-JavaScript submit gets a redirect and the server-rendered
        // success card (validation failures already redirect back with
        // errors + old input on this path).
        if (! $request->expectsJson()) {
            return redirect()->route('marketing.contact')->with('contact_sent', true);
        }

        return response()->json(['status' => 'sent']);
    }
}
