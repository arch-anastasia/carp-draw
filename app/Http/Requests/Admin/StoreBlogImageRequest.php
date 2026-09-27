<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\StoreBlogImageRequest as ApiStoreBlogImageRequest;
use App\Services\Ingest\ImageIngestor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class StoreBlogImageRequest extends ApiStoreBlogImageRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'alt' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'image', 'max:'.intdiv(ImageIngestor::MAX_BYTES, 1024)],
            'data' => ['prohibited'],
        ]);
    }

    public function nameHint(): string
    {
        $name = trim((string) ($this->input('name') ?? ''));

        if ($name === '') {
            $name = trim((string) $this->input('alt'));
        }

        if ($name === '') {
            $name = pathinfo((string) $this->file('file')?->getClientOriginalName(), PATHINFO_FILENAME);
        }

        return Str::limit($name, 80, '');
    }
}
