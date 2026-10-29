<?php

namespace App\Http\Requests\Api;

use App\Services\Ingest\ImageIngestor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StoreBlogImageRequest extends FormRequest
{
    public const MAX_DIMENSION = 8000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * `image` refuses an SVG, which is the point: an SVG is a script
             * host, and one served from our own origin is stored XSS on a page
             * a reader trusts. Everything else is re-encoded to webp on the way
             * in, so whatever a file was carrying does not survive the trip.
             */
            'file' => ['required_without:data', 'file', 'image', 'max:'.intdiv(ImageIngestor::MAX_BYTES, 1024)],
            'data' => ['required_without:file', 'string'],

            /*
             * Required, not optional. The alt text is the image for a reader
             * using a screen reader and for anything reading the page without
             * eyes, and an agent that has just decided what to illustrate is
             * the one thing in this pipeline that knows what the picture shows.
             */
            'alt' => ['required', 'string', 'max:255'],

            /* Names the stored file. Falls back to the alt text. */
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.image' => 'The file must be a raster image - jpeg, png, gif, webp or avif. SVG is refused: it can carry script, and this one would be served from the blog\'s own origin.',
            'data.required_without' => 'Send the picture as a multipart `file`, or as base64 bytes in `data`.',
            'file.required_without' => 'Send the picture as a multipart `file`, or as base64 bytes in `data`.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $bytes = $this->imageBytes();
            $field = $this->hasFile('file') ? 'file' : 'data';

            if ($bytes === null) {
                $validator->errors()->add('data', 'The data field must be a base64-encoded image.');

                return;
            }

            if (strlen($bytes) > ImageIngestor::MAX_BYTES) {
                $validator->errors()->add($field, 'The image may not be larger than '.intdiv(ImageIngestor::MAX_BYTES, 1024 * 1024).'MB.');

                return;
            }

            $size = @getimagesizefromstring($bytes);

            if (! is_array($size)) {
                $validator->errors()->add($field, 'The image could not be read. Send raster bytes - jpeg, png, gif, webp or avif.');

                return;
            }

            if ((int) $size[0] > self::MAX_DIMENSION || (int) $size[1] > self::MAX_DIMENSION) {
                $validator->errors()->add($field, 'The image may not be larger than '.self::MAX_DIMENSION.' pixels on a side.');
            }
        });
    }

    public function imageBytes(): ?string
    {
        $file = $this->file('file');

        if ($file instanceof UploadedFile) {
            return (string) file_get_contents((string) $file->getRealPath());
        }

        /*
         * A data URI is what most tooling hands you when it produces an image,
         * so the prefix is dropped rather than 422'd over. What follows it is
         * the same base64 either way.
         */
        $data = (string) preg_replace('/^data:image\/[a-z0-9.+-]+;base64,/i', '', trim((string) $this->input('data')));

        $decoded = base64_decode($data, true);

        return $decoded === false ? null : $decoded;
    }

    public function nameHint(): string
    {
        $name = trim((string) ($this->input('name') ?? ''));

        if ($name === '') {
            $name = trim((string) $this->input('alt'));
        }

        return Str::limit($name, 80, '');
    }
}
