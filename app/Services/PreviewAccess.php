<?php

namespace App\Services;

use Illuminate\Http\Request;

class PreviewAccess
{
    public function granted(Request $request): bool
    {
        return $this->isAdmin($request) || $this->hasApiToken($request);
    }

    public function isAdmin(Request $request): bool
    {
        return $request->user()?->is_admin === true;
    }

    private function hasApiToken(Request $request): bool
    {
        $configured = trim((string) config('blog.api.token', ''));

        /*
         * An unset token closes this door rather than opening it. The other
         * reading - blank means no check - turns every draft on a server that
         * forgot the variable into a public URL.
         */
        if ($configured === '') {
            return false;
        }

        $presented = trim((string) $request->bearerToken());

        return $presented !== '' && hash_equals($configured, $presented);
    }
}
