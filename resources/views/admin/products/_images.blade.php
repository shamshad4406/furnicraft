<x-admin.card title="Product images"
              subtitle="Drag to reorder · click the star to set the primary image"
              icon="bi-images">

    <x-slot:actions>
        <form method="POST" action="{{ route('admin.products.images.store', $product) }}"
              enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="file" name="images[]" accept="image/*" multiple required
                   class="form-control form-control-sm" style="max-width:280px">
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-upload me-1"></i> Upload
            </button>
        </form>
    </x-slot:actions>

    @if ($product->images->isEmpty())
        <div class="ad-empty">
            <i class="bi bi-images"></i>
            No images yet. Upload up to 10 — each one is converted to WebP with a thumbnail.
        </div>
    @else
        <div class="row g-3" id="productImages"
             data-reorder-url="{{ route('admin.products.images.reorder', $product) }}">
            @foreach ($product->images as $image)
                <div class="col-6 col-md-4 col-xl-3" data-image-id="{{ $image->id }}">
                    <div class="border rounded h-100 d-flex flex-column {{ $image->is_primary ? 'border-warning border-2' : '' }}">
                        <div class="position-relative" style="cursor:grab" draggable="true" data-drag-handle>
                            <img src="{{ $image->thumbnail_url }}" alt="{{ $image->alt_text }}"
                                 class="w-100" style="height:150px;object-fit:cover;border-radius:.35rem .35rem 0 0;background:#f1f5f9">

                            @if ($image->is_primary)
                                <span class="badge text-bg-warning position-absolute top-0 start-0 m-2">
                                    <i class="bi bi-star-fill me-1"></i>Primary
                                </span>
                            @endif
                        </div>

                        <div class="p-2 d-flex flex-column gap-2 flex-grow-1">
                            <form method="POST" action="{{ route('admin.products.images.update', [$product, $image]) }}"
                                  class="d-flex gap-1">
                                @csrf @method('PUT')
                                <input type="text" name="alt_text" value="{{ $image->alt_text }}"
                                       class="form-control form-control-sm" placeholder="Alt text" aria-label="Alt text">
                                <button class="btn btn-sm btn-outline-secondary" title="Save alt text">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>

                            <div class="d-flex gap-1 justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:.68rem">
                                    {{ $image->width }}×{{ $image->height }}
                                    @if ($image->size) · {{ number_format($image->size / 1024) }} KB @endif
                                </span>

                                <span class="d-flex gap-1">
                                    @unless ($image->is_primary)
                                        <form method="POST" action="{{ route('admin.products.images.primary', [$product, $image]) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-sm btn-outline-warning" title="Make primary">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        </form>
                                    @endunless

                                    <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}"
                                          data-confirm="Delete this image?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.card>
