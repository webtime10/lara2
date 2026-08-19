<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Менеджер изображений</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; padding: 16px; }
        .fm-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:16px; }
        .fm-grid { display:flex; flex-wrap:wrap; gap:12px; }
        .fm-item {
            width:130px; cursor:pointer; border:2px solid transparent;
            border-radius:6px; overflow:hidden; background:#fff;
            box-shadow:0 1px 3px rgba(0,0,0,.15); transition:border-color .15s;
            position:relative;
        }
        .fm-item:hover { border-color:#007bff; }
        .fm-item.selected { border-color:#28a745; }
        .fm-item img { width:100%; height:100px; object-fit:cover; display:block; }
        .fm-item-name {
            font-size:11px; padding:4px 6px; white-space:nowrap;
            overflow:hidden; text-overflow:ellipsis; color:#555;
        }
        .fm-item-del {
            position:absolute; top:4px; right:4px;
            background:rgba(220,53,69,.85); color:#fff;
            border:none; border-radius:3px; font-size:11px;
            padding:1px 5px; cursor:pointer; display:none;
        }
        .fm-item:hover .fm-item-del { display:block; }
        .fm-drop {
            border:2px dashed #adb5bd; border-radius:6px;
            padding:24px; text-align:center; color:#888; cursor:pointer;
            margin-bottom:16px; transition:border-color .15s;
        }
        .fm-drop.dragover { border-color:#007bff; color:#007bff; }
        .fm-empty { color:#999; padding:24px 0; }
        #fm-progress { display:none; margin-bottom:10px; }
    </style>
</head>
<body>

<div class="fm-toolbar">
    <label class="btn btn-sm btn-primary mb-0">
        <i class="fas fa-upload"></i> Загрузить
        <input type="file" id="fm-file-input" accept="image/*" multiple style="display:none">
    </label>
    <span class="text-muted small">JPG, PNG, GIF, WEBP — макс. 4 МБ</span>
</div>

<div id="fm-drop" class="fm-drop">
    <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block"></i>
    Перетащите файлы сюда или нажмите «Загрузить»
</div>

<div id="fm-progress">
    <div class="progress">
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%">Загрузка…</div>
    </div>
</div>

<div id="fm-alert"></div>

<div class="fm-grid" id="fm-grid">
    @forelse($files as $file)
        <div class="fm-item" data-url="{{ $file['url'] }}" data-name="{{ $file['name'] }}">
            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy">
            <div class="fm-item-name" title="{{ $file['name'] }}">{{ $file['name'] }}</div>
            <button class="fm-item-del" type="button" title="Удалить"><i class="fas fa-times"></i></button>
        </div>
    @empty
        <p class="fm-empty">Изображений пока нет — загрузите первое.</p>
    @endforelse
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function ($) {
    var target = {{ json_encode($target) }};
    var thumb  = {{ json_encode($thumb) }};
    var uploadUrl = '{{ route('admin.filemanager.upload') }}';
    var deleteUrl = '{{ route('admin.filemanager.delete') }}';
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Select image and close
    function selectImage(url) {
        if (window.opener && target) {
            var $input = window.opener.$('#' + target);
            var $img   = window.opener.$('#' + thumb);
            if ($input.length) $input.val(url);
            if ($img.length)   $img.attr('src', url);
        }
        window.close();
    }

    // Click on item
    $(document).on('click', '.fm-item', function (e) {
        if ($(e.target).closest('.fm-item-del').length) return;
        selectImage($(this).data('url'));
    });

    // Delete item
    $(document).on('click', '.fm-item-del', function (e) {
        e.stopPropagation();
        var $item = $(this).closest('.fm-item');
        var name  = $item.data('name');
        if (!confirm('Удалить «' + name + '»?')) return;
        $.post(deleteUrl, { _token: csrfToken, name: name }, function () {
            $item.remove();
        });
    });

    // Upload via button
    $('#fm-file-input').on('change', function () {
        uploadFiles(this.files);
        this.value = '';
    });

    // Drag & drop
    var $drop = $('#fm-drop');
    $drop.on('dragover', function (e) { e.preventDefault(); $drop.addClass('dragover'); });
    $drop.on('dragleave drop', function (e) { e.preventDefault(); $drop.removeClass('dragover'); });
    $drop.on('drop', function (e) { uploadFiles(e.originalEvent.dataTransfer.files); });
    $drop.on('click', function () { $('#fm-file-input').trigger('click'); });

    function uploadFiles(files) {
        if (!files || !files.length) return;
        var $prog = $('#fm-progress').show();
        var pending = files.length;

        for (var i = 0; i < files.length; i++) {
            (function (file) {
                var fd = new FormData();
                fd.append('_token', csrfToken);
                fd.append('file', file);
                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function (r) {
                        addItem(r.url, r.name);
                    },
                    error: function (xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.errors
                            ? Object.values(xhr.responseJSON.errors).flat().join(', ')
                            : 'Ошибка загрузки';
                        showAlert(msg, 'danger');
                    },
                    complete: function () {
                        if (--pending === 0) $prog.hide();
                    }
                });
            })(files[i]);
        }
    }

    function addItem(url, name) {
        var html = '<div class="fm-item" data-url="' + url + '" data-name="' + name + '">'
            + '<img src="' + url + '" alt="' + name + '" loading="lazy">'
            + '<div class="fm-item-name" title="' + name + '">' + name + '</div>'
            + '<button class="fm-item-del" type="button" title="Удалить"><i class="fas fa-times"></i></button>'
            + '</div>';
        var $grid = $('#fm-grid');
        $grid.find('.fm-empty').remove();
        $grid.prepend(html);
    }

    function showAlert(msg, type) {
        $('#fm-alert').html('<div class="alert alert-' + type + ' alert-dismissible">'
            + msg
            + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
    }
})(jQuery);
</script>
</body>
</html>
