<?php

namespace App\Http\Requests\Api;

use App\Models\Post;

class StoreBlogPostRequest extends BlogPostRequest
{
    protected function existingPost(): ?Post
    {
        return null;
    }
}
