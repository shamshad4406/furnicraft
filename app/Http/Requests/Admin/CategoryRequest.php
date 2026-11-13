<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            // Unique across every row, soft-deleted included, because the
            // database index covers them too.
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:400'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'slug.unique' => 'This slug is already used by another category.',
        ];
    }

    /**
     * A category may not become its own descendant.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');

            if (! $category || ! $parentId) {
                return;
            }

            if (in_array((int) $parentId, $category->descendantIds(), true)) {
                $validator->errors()->add('parent_id', 'A category cannot be moved inside itself or one of its sub-categories.');
            }
        });
    }
}
