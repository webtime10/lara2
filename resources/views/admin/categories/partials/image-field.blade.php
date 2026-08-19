{{-- Image picker field. Variables: $imageValue (current path or '') --}}
<div class="form-group">
    <label>Изображение категории</label>
    <div class="card" style="max-width:260px;">
        <img id="thumb-category-image"
             src="{{ $imageValue ? asset($imageValue) : '' }}"
             alt=""
             class="card-img-top"
             style="height:160px; object-fit:cover; background:#eee; {{ !$imageValue ? 'display:none;' : '' }}">
        <div id="thumb-category-placeholder"
             style="height:160px; background:#eee; display:flex; align-items:center; justify-content:center; color:#bbb; {{ $imageValue ? 'display:none;' : '' }}">
            <i class="fas fa-image fa-4x"></i>
        </div>
        <input type="hidden" name="image" id="input-category-image" value="{{ $imageValue }}">
        <div class="card-body p-2 d-flex">
            <button type="button" class="btn btn-primary btn-sm mr-2"
                    data-toggle="modal" data-target="#modal-filemanager">
                <i class="fas fa-pencil-alt"></i> Выбрать
            </button>
            <button type="button" class="btn btn-warning btn-sm" id="btn-clear-image">
                <i class="fas fa-trash-alt"></i> Очистить
            </button>
        </div>
    </div>
    <small class="form-text text-muted">JPG, PNG, GIF, WEBP</small>
</div>

{{-- Bootstrap Modal (OpenCart-style) --}}
<div class="modal fade" id="modal-filemanager" tabindex="-1" role="dialog" aria-labelledby="modal-filemanager-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-filemanager-label">
                    <i class="fas fa-images mr-1"></i> Менеджер изображений
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Toolbar --}}
                <div class="d-flex align-items-center mb-3" style="gap:8px;">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-fm-upload">
                        <i class="fas fa-upload"></i> Загрузить
                    </button>
                    <input type="text" id="fm-search-input" class="form-control form-control-sm" style="max-width:220px;" placeholder="Поиск…">
                    <button type="button" class="btn btn-default btn-sm" id="btn-fm-search">
                        <i class="fas fa-search"></i>
                    </button>
                    <button type="button" class="btn btn-default btn-sm ml-auto" id="btn-fm-refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <hr class="mt-0">
                {{-- Image grid (loaded via AJAX) --}}
                <div id="fm-list-wrap">
                    <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Загрузка…</div>
                </div>
            </div>
        </div>
    </div>
</div>
