<div class="row row-cols-3 row-cols-md-4 g-2" id="fm-image-grid">
    @forelse($files as $file)
        <div class="col">
            <div class="card h-100 fm-thumb-card" style="cursor:pointer;" data-url="{{ $file['url'] }}" data-name="{{ $file['name'] }}">
                <img src="{{ $file['url'] }}" class="card-img-top" alt="{{ $file['name'] }}"
                     style="height:110px; object-fit:cover;">
                <div class="card-body p-1 d-flex align-items-center justify-content-between">
                    <small class="text-truncate text-muted" style="max-width:80%;" title="{{ $file['name'] }}">{{ $file['name'] }}</small>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger fm-delete-btn"
                            data-name="{{ $file['name'] }}" title="Удалить"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5">
            <i class="fas fa-image fa-3x mb-3 d-block"></i>
            Изображений пока нет — загрузите первое
        </div>
    @endforelse
</div>
