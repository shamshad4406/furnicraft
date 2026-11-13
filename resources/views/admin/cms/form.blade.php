@extends('admin.layouts.app')

@php $editing = $page->exists; @endphp

@section('title', $editing ? 'Edit Page' : 'Add Page')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.cms.index') }}">Pages</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $page->title : 'Add' }}</li>
@endsection

@section('content')

<form method="POST" action="{{ $editing ? route('admin.cms.update', $page) : route('admin.cms.store') }}" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-admin.card title="Page content" icon="bi-file-earmark-richtext">
                <div class="row g-3">
                    <x-admin.input name="title" label="Page title" :value="$page->title" required col="col-md-8" />
                    <x-admin.input name="sort_order" label="Sort order" type="number" min="0"
                                   :value="$page->sort_order ?? 0" col="col-md-4" />

                    <x-admin.input name="slug" label="URL slug" :value="$page->slug" col="col-12"
                                   :disabled="$page->is_system"
                                   :help="$page->is_system
                                        ? 'This page is linked from the website, so its address is fixed.'
                                        : 'Leave blank to generate it from the title. The page will live at /your-slug.'" />

                    <x-admin.textarea name="excerpt" label="Short intro" :value="$page->excerpt" rows="2"
                                      help="Shown above the content and used as the meta description when none is set." />

                    <x-admin.textarea name="content" label="Content" :value="$page->content" rows="18"
                                      style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.84rem"
                                      help="Markdown: ## for a heading, **bold**, - for bullet points. Raw HTML is stripped." />
                </div>
            </x-admin.card>

            <x-admin.card title="SEO" icon="bi-search" class="mt-3">
                <div class="row g-3">
                    <x-admin.input name="meta_title" label="Meta title" :value="$page->meta_title" col="col-12" />
                    <x-admin.textarea name="meta_description" label="Meta description" :value="$page->meta_description" rows="2" />
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Visibility" icon="bi-eye">
                <div class="row g-3">
                    <x-admin.switcher name="is_active" col="col-12" :checked="$page->is_active ?? true">
                        Published on the website
                    </x-admin.switcher>
                    <x-admin.switcher name="show_in_footer" col="col-12" :checked="$page->show_in_footer">
                        Link from the website footer
                    </x-admin.switcher>
                </div>
            </x-admin.card>

            @if ($editing)
                <x-admin.card title="Preview" icon="bi-box-arrow-up-right" class="mt-3">
                    <a href="{{ route('page', $page->slug) }}" target="_blank" rel="noopener"
                       class="btn btn-outline-secondary btn-sm w-100">
                        Open /{{ $page->slug }}
                    </a>
                </x-admin.card>
            @endif

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-lg me-1"></i> {{ $editing ? 'Save Page' : 'Create Page' }}
                </button>
                <a href="{{ route('admin.cms.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

@endsection
