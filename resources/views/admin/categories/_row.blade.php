<tr>
    <td>
        <div class="d-flex align-items-center gap-2" @if ($depth) style="padding-left:1.75rem" @endif>
            @if ($depth)
                <i class="bi bi-arrow-return-right text-muted small"></i>
            @endif

            @if ($category->image_url)
                <img src="{{ $category->image_url }}" alt=""
                     style="width:38px;height:30px;object-fit:cover;border-radius:.25rem;background:#f1f5f9">
            @else
                <span class="d-grid" style="width:38px;height:30px;border-radius:.25rem;background:#f1f5f9;place-items:center">
                    <i class="bi bi-image text-muted small"></i>
                </span>
            @endif

            <div class="lh-sm">
                <div class="fw-semibold">{{ $category->name }}</div>
                @if ($depth === 0 && $category->children_count ?? false)
                    <div class="text-muted" style="font-size:.72rem">{{ $category->children->count() }} sub-categories</div>
                @endif
            </div>
        </div>
    </td>
    <td class="text-muted" style="font-size:.8rem"><code>{{ $category->slug }}</code></td>
    <td>
        <span class="badge text-bg-light border">{{ $category->products_count ?? 0 }}</span>
    </td>
    <td class="text-muted">{{ $category->sort_order }}</td>
    <td>
        <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
            {{ $category->is_active ? 'Active' : 'Hidden' }}
        </span>
    </td>
    <td class="text-end text-nowrap">
        @can('categories.edit')
            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary"
               data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>

            <form method="POST" action="{{ route('admin.categories.toggle', $category) }}" class="d-inline">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-{{ $category->is_active ? 'warning' : 'success' }}"
                        data-bs-toggle="tooltip" title="{{ $category->is_active ? 'Hide' : 'Show' }}">
                    <i class="bi {{ $category->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                </button>
            </form>
        @endcan

        @can('categories.delete')
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline"
                  data-confirm="Delete the category {{ $category->name }}?">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        @endcan
    </td>
</tr>
