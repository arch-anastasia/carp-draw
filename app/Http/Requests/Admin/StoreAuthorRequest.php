<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('authors', 'username')],
            'role' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'file', 'image', 'max:8192'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'links' => ['nullable', 'array', 'max:8'],
            'links.*.label' => ['nullable', 'string', 'max:60'],
            'links.*.url' => ['nullable', 'url', 'max:500'],
            'profile_is_published' => ['boolean'],
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'A handle is lowercase letters, numbers and dashes - it becomes /blog/authors/{handle}.',
            'name.required' => 'Give the writer the name that should print in their byline.',
        ];
    }
}
