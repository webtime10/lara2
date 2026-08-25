{{-- Shared file manager modal (one per page). --}}
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
                <div id="fm-list-wrap">
                    <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Загрузка…</div>
                </div>
            </div>
        </div>
    </div>
</div>
