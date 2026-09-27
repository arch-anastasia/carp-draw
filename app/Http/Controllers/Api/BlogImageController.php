<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBlogImageRequest;
use App\Http\Resources\BlogImageResource;
use App\Models\Media;
use App\Services\Ingest\ImageIngestor;
use Illuminate\Http\JsonResponse;

class BlogImageController extends Controller
{
    private const DIRECTORY = 'blog';

    public function __construct(private readonly ImageIngestor $images) {}

    public function store(StoreBlogImageRequest $request): JsonResponse
    {
        /* Non-null: the request refuses anything that does not decode. */
        $bytes = (string) $request->imageBytes();

        $url = $this->images->storeBytes($bytes, self::DIRECTORY, $request->nameHint());

        /*
         * forUrl() is find-or-create, so a re-upload of the same picture
         * adopts the row it already had - and updates the alt text, because a
         * second upload with a better description is a correction, not a
         * duplicate.
         */
        $media = Media::forUrl($url, (string) $request->validated('alt'));

        return (new BlogImageResource($media->measure()))
            ->response()
            ->setStatusCode(201);
    }
}
