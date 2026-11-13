<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('brand')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'slug.unique' => 'This slug is already used by another brand.',
        ];
    }
}
