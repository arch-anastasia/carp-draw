<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One uploaded picture, as the write API hands it back.
 *
 * `url` is the field that matters - it is what goes into a post's `cover_image`
 * or into its body. `markdown` is the same URL already written out as a body
 * image, alt text included, because that is what the caller was going to do
 * with it and getting the alt text there is the whole reason it was asked for.
 *
 * @mixin Media
 */
class BlogImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Media $media */
        $media = $this->resource;

        return [
            'id' => $media->id,
            'url' => $media->url,
            'alt' => $media->alt,
            'markdown' => '!['.str_replace(['[', ']'], '', (string) $media->alt).']('.$media->url.')',

            'mime' => $media->mime,
            'width' => $media->width,
            'height' => $media->height,
            'bytes' => $media->bytes,
        ];
    }
}
