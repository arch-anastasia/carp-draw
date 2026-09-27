<?php

namespace App\Providers;

use App\Listeners\StripeEventListener;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\Drawing\ImageProvider;
use App\Services\Drawing\ProviderRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Both generation backends come from one registry, which reads the
         * driver catalogue in config/drawing.php. A real key gets the real
         * model; a driver that needs a key and has none is downgraded to the
         * fake stand-in so local and CI can exercise the whole pipeline —
         * production is protected by ProjectService::isLive(), which refuses
         * stand-ins there.
         *
         * Deliberately not resolved here: the driver is read at RESOLVE time,
         * so a test that switches backends mid-run gets the backend it asked
         * for rather than whichever one was configured at boot.
         */
        $this->app->bind(
            ImageProvider::class,
            fn ($app) => $app->make(ProviderRegistry::class)->imageProvider(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('content-api', fn (Request $request) => Limit::perMinute((int) config('blog.api.throttle', 60))->by(hash('sha256', (string) $request->bearerToken())));
        // Cashier uses UUID-keyed models like every other application table.
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        /*
         * Listener auto-discovery is off (bootstrap/app.php) — everything is
         * registered here, once.
         */
        Event::listen(WebhookReceived::class, StripeEventListener::class);

        // Sign in with Apple is a community Socialite provider and has to be
        // wired to the Socialite manager by name.
        Event::listen(
            SocialiteWasCalled::class,
            fn (SocialiteWasCalled $event) => $event->extendSocialite('apple', Provider::class),
        );

        // Share branding config with all Blade views.
        View::share('branding', config('branding'));

        /*
         * The marketing auth modal renders a button per CONFIGURED provider —
         * the same membership-and-credentials rule the sign-in page applies,
         * kept in one place by a composer so the two can never disagree.
         */
        View::composer('components.marketing.auth-modal', function ($view): void {
            $view->with('socialProviders', array_values(array_filter(
                config('signin.providers', []),
                fn (string $provider) => (bool) config("services.{$provider}.client_id"),
            )));
        });
    }
}
