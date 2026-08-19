@extends('admin.layouts.layout')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Новый регион</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Ваш идеальный регион</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Категории</a></li>
                        <li class="breadcrumb-item active">Создание</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <button type="submit" form="categoryForm" class="btn btn-primary float-right" title="Сохранить" aria-label="Сохранить">
                                <i class="fas fa-save"></i> <span>Сохранить</span>
                            </button>
                        </div>
                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form id="categoryForm" action="{{ route('admin.categories.store') }}" method="POST">
                                @csrf

                                @include('admin.categories.partials.image-field', ['imageValue' => old('image', '')])

                                @if($languages->isEmpty())
                                    <p class="text-muted small mb-3">
                                        Языков пока нет — добавьте их в разделе <a href="{{ route('admin.languages.index') }}">«Языки»</a>.
                                    </p>
                                @else
                                    <ul class="nav nav-tabs" id="categoryLangTabs" role="tablist">
                                        @foreach($languages as $index => $language)
                                            <li class="nav-item">
                                                <a class="nav-link @if($index === 0) active @endif"
                                                   id="tab-lang-{{ $language->code }}"
                                                   data-toggle="tab"
                                                   href="#pane-lang-{{ $language->code }}"
                                                   role="tab"
                                                   aria-controls="pane-lang-{{ $language->code }}"
                                                   aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                                    {{ strtolower($language->code) }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <div class="tab-content border border-top-0 rounded-bottom p-3 mb-4 bg-light" id="categoryLangTabsContent">
                                        @foreach($languages as $index => $language)
                                            @php $c = $language->code; @endphp
                                            <div class="tab-pane fade @if($index === 0) show active @endif"
                                                 id="pane-lang-{{ $c }}"
                                                 role="tabpanel"
                                                 aria-labelledby="tab-lang-{{ $c }}">
                                                <div class="form-group">
                                                    <label for="name_{{ $c }}">Название региона @if($language->is_default)<span class="text-danger">*</span>@endif</label>
                                                    <input type="text" name="name_{{ $c }}" id="name_{{ $c }}"
                                                           class="form-control form-control-lg @error('name_'.$c) is-invalid @enderror"
                                                           value="{{ old('name_'.$c) }}"
                                                           placeholder="Например: Ноутбуки, Аксессуары…"
                                                           {{ $language->is_default ? 'required' : '' }}>
                                                    @error('name_'.$c)
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                                <div class="form-group">
                                                    <label for="description_{{ $c }}">Описание</label>
                                                    <textarea name="description_{{ $c }}" id="description_{{ $c }}" class="form-control js-category-description" rows="4">{{ old('description_'.$c) }}</textarea>
                                                </div>
                                                @include('admin.categories.partials.ideal-region-fields', ['c' => $c])
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="form-group">
                                    <label for="manufacturer_id">Производитель <span class="text-danger">*</span></label>
                                    <select name="manufacturer_id" id="manufacturer_id" class="form-control @error('manufacturer_id') is-invalid @enderror" required>
                                        <option value="">— Выберите —</option>
                                        @foreach($manufacturers as $m)
                                            <option value="{{ $m->id }}" {{ (string) old('manufacturer_id') === (string) $m->id ? 'selected' : '' }}>
                                                {{ $m->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('manufacturer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="parent_id">Родитель — внутри какого региона</label>
                                    <select name="parent_id" id="parent_id" class="form-control @error('parent_id') is-invalid @enderror">
                                        <option value="">— В корне каталога (без родителя, верхний уровень) —</option>
                                        @foreach($parentOptions as $opt)
                                            <option value="{{ $opt['id'] }}" {{ (string) old('parent_id') === (string) $opt['id'] ? 'selected' : '' }}>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Только для вложенности; на название нового региона не влияет.</small>
                                    @error('parent_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="sort_order">Порядок сортировки</label>
                                    <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0" style="max-width: 12rem;">
                                </div>

                                <div class="form-group">
                                    <input type="hidden" name="status" value="0">
                                    <label><input type="checkbox" name="status" value="1" {{ old('status', true) ? 'checked' : '' }}> Активна</label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

@section('scripts')
<script>
(function ($) {
    $(function () {
        // Image picker — OpenCart-style modal
        var fmListUrl = '{{ route('admin.filemanager.list') }}';
        var fmUploadUrl = '{{ route('admin.filemanager.upload') }}';
        var fmDeleteUrl = '{{ route('admin.filemanager.delete') }}';
        var fmCsrf = '{{ csrf_token() }}';

        function fmLoad(search) {
            var url = fmListUrl;
            if (search) url += '?search=' + encodeURIComponent(search);
            $('#fm-list-wrap').load(url);
        }

        $('#modal-filemanager').on('show.bs.modal', function () { fmLoad(); });
        $('#btn-fm-refresh').on('click', function () { fmLoad($('#fm-search-input').val()); });
        $('#btn-fm-search').on('click', function () { fmLoad($('#fm-search-input').val()); });
        $('#fm-search-input').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); fmLoad($(this).val()); } });

        // Select image
        $(document).on('click', '.fm-thumb-card', function (e) {
            if ($(e.target).closest('.fm-delete-btn').length) return;
            var url = $(this).data('url');
            $('#input-category-image').val(url);
            $('#thumb-category-image').attr('src', url).show();
            $('#thumb-category-placeholder').hide();
            $('#modal-filemanager').modal('hide');
        });

        // Delete image
        $(document).on('click', '.fm-delete-btn', function (e) {
            e.stopPropagation();
            var name = $(this).data('name');
            var $card = $(this).closest('.col');
            if (!confirm('Удалить «' + name + '»?')) return;
            $.post(fmDeleteUrl, { _token: fmCsrf, name: name }, function () {
                $card.remove();
                if (!$('#fm-image-grid .col').length) {
                    fmLoad();
                }
            });
        });

        // Upload
        $('#btn-fm-upload').on('click', function () {
            $('<input type="file" accept="image/*" multiple style="display:none">').appendTo('body')
                .trigger('click')
                .on('change', function () {
                    var files = this.files;
                    var $btn = $('#btn-fm-upload').prop('disabled', true);
                    var pending = files.length;
                    for (var i = 0; i < files.length; i++) {
                        var fd = new FormData();
                        fd.append('_token', fmCsrf);
                        fd.append('file', files[i]);
                        $.ajax({
                            url: fmUploadUrl, type: 'POST',
                            data: fd, processData: false, contentType: false,
                            error: function (xhr) {
                                var msg = xhr.responseJSON && xhr.responseJSON.errors
                                    ? Object.values(xhr.responseJSON.errors).flat().join(', ')
                                    : 'Ошибка загрузки';
                                alert(msg);
                            },
                            complete: function () {
                                if (--pending === 0) {
                                    $btn.prop('disabled', false);
                                    fmLoad($('#fm-search-input').val());
                                }
                            }
                        });
                    }
                    $(this).remove();
                });
        });

        $('#btn-clear-image').on('click', function () {
            $('#input-category-image').val('');
            $('#thumb-category-image').attr('src', '').hide();
            $('#thumb-category-placeholder').show();
        });

        $('.js-category-description').summernote({
            height: 220,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    });
})(jQuery);
</script>
@endsection
