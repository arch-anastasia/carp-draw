<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlogImageRequest;
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

        $media = Media::forUrl($url, (string) $request->validated('alt'));

        return (new BlogImageResource($media->measure()))
            ->response()
            ->setStatusCode(201);
    }
}
