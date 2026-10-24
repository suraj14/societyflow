<?php

namespace App\Support;

use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Laravel's Eloquent user provider, except that the e-mail address is matched whatever its letter case.
 *
 * Both the session login (Auth::attempt) and the password broker (forgot / reset password) find the account through
 * this provider. The stock provider compares `email = ?`, which MySQL's default collation treats case-insensitively
 * but SQLite does not: an account stored as NAME@EXAMPLE.COM could neither sign in nor receive a reset link when the
 * person typed name@example.com. Registration already stores new addresses in lower case; older rows are not
 * rewritten, so the match is made on LOWER(email) on every engine.
 */
class CaseInsensitiveUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } elseif ($key === 'email') {
                $query->whereEmail($value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
