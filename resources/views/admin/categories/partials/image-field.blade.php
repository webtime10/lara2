{{-- Image picker per language. Vars: $imageValue, $inputName, $fieldId, $label --}}
@php
    $inputName = $inputName ?? 'image';
    $fieldId = $fieldId ?? 'main';
    $label = $label ?? 'Изображение категории';
@endphp
<div class="form-group js-lang-image-field" data-lang="{{ $fieldId }}">
    <label>{{ $label }} <span class="text-muted small">(язык {{ $fieldId }})</span></label>
    <div class="card" style="max-width:260px;">
        <img id="thumb-category-image-{{ $fieldId }}"
             src="{{ $imageValue ? asset($imageValue) : '' }}"
             alt=""
             class="card-img-top js-cat-image-thumb"
             style="height:160px; object-fit:cover; background:#eee; {{ !$imageValue ? 'display:none;' : '' }}">
        <div id="thumb-category-placeholder-{{ $fieldId }}"
             class="js-cat-image-placeholder"
             style="height:160px; background:#eee; display:flex; align-items:center; justify-content:center; color:#bbb; {{ $imageValue ? 'display:none;' : '' }}">
            <i class="fas fa-image fa-4x"></i>
        </div>
        <input type="hidden" name="{{ $inputName }}" id="input-category-image-{{ $fieldId }}" class="js-cat-image-input" value="{{ $imageValue }}">
        <div class="card-body p-2 d-flex">
            <button type="button" class="btn btn-primary btn-sm mr-2 js-open-filemanager"
                    data-lang="{{ $fieldId }}"
                    data-toggle="modal" data-target="#modal-filemanager">
                <i class="fas fa-pencil-alt"></i> Выбрать
            </button>
            <button type="button" class="btn btn-warning btn-sm js-clear-lang-image" data-lang="{{ $fieldId }}">
                <i class="fas fa-trash-alt"></i> Очистить
            </button>
        </div>
    </div>
    <small class="form-text text-muted">JPG, PNG, GIF, WEBP — своё фото для этого языка</small>
</div>
