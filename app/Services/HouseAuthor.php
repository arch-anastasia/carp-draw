<?php

namespace App\Services;

use App\Models\Author;

/**
 * The editorial identity house copy is published under.
 *
 * Every article carries a byline, because an unsigned page gives a reader
 * nothing to weigh - and because Google's guidance on who wrote a piece is not
 * advisory for advice content. A post nobody has been attached to is credited
 * here instead of printing nothing.
 *
 * `find()` never writes, and is what a page render uses: a GET must not create
 * an author row. `resolve()` is reserved for write paths that need to ensure
 * an article can never be left unsigned.
 *
 * @see config/blog.php
 */
class HouseAuthor
{
    public function find(): ?Author
    {
        $username = (string) config('blog.house_author.username', '');

        if ($username === '') {
            return null;
        }

        return Author::query()->where('username', $username)->first();
    }

    public function resolve(): Author
    {
        /** @var array<string, string|null> $house */
        $house = (array) config('blog.house_author', []);
        $username = (string) ($house['username'] ?? 'house');
        $name = (string) ($house['name'] ?? 'Editors');

        return Author::query()->firstOrCreate(['username' => $username], [
            'name' => $name,
            'bio' => $house['bio'] ?? null,
            'role' => $house['role'] ?? null,
            'profile_is_published' => true,
        ]);
    }
}
