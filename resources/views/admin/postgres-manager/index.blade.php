@extends('admin.layouts.layout')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>PostgreSQL Manager</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Home</a></li>
                    <li class="breadcrumb-item active">PostgreSQL Manager</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if($connectionError)
            <div class="alert alert-danger">
                <strong>Ошибка подключения к PostgreSQL:</strong> {{ $connectionError }}
            </div>
        @endif

        <div class="card card-outline card-primary">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="pg-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'dashboard' ? 'active' : '' }}"
                           href="{{ route('admin.postgres-manager.index', ['tab' => 'dashboard']) }}">
                            Дашборд и Схема
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'viewer' ? 'active' : '' }}"
                           href="{{ route('admin.postgres-manager.index', ['tab' => 'viewer']) }}">
                            Просмотр таблиц (Data Viewer)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === 'console' ? 'active' : '' }}"
                           href="{{ route('admin.postgres-manager.index', ['tab' => 'console']) }}">
                            SQL Консоль (Runner)
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                @if($tab === 'dashboard')
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="info-box {{ ($pgvector['installed'] ?? false) ? 'bg-success' : 'bg-warning' }}">
                                <span class="info-box-icon"><i class="fas fa-puzzle-piece"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">pgvector</span>
                                    <span class="info-box-number">
                                        @if($pgvector['installed'] ?? false)
                                            Installed
                                            <small class="d-block font-weight-normal">v{{ $pgvector['version'] }}</small>
                                        @else
                                            Not Installed
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-database"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Tables (public)</span>
                                    <span class="info-box-number">{{ count($tables) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Таблица</th>
                                    <th style="width:160px">Записей</th>
                                    <th style="width:180px">Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tables as $i => $tableName)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td><code>{{ $tableName }}</code></td>
                                        <td>{{ number_format($tableCounts[$tableName] ?? 0, 0, '.', ' ') }}</td>
                                        <td>
                                            <a href="{{ route('admin.postgres-manager.index', ['tab' => 'viewer', 'table' => $tableName]) }}"
                                               class="btn btn-sm btn-primary">
                                                Просмотреть данные
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Таблиц не найдено</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($tab === 'viewer')
                    <form method="get" action="{{ route('admin.postgres-manager.index') }}" class="form-inline mb-3">
                        <input type="hidden" name="tab" value="viewer">
                        <label class="mr-2" for="pg-table-select">Таблица</label>
                        <select name="table" id="pg-table-select" class="form-control mr-2" onchange="this.form.submit()">
                            @foreach($tables as $tableName)
                                <option value="{{ $tableName }}" {{ $selectedTable === $tableName ? 'selected' : '' }}>
                                    {{ $tableName }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Открыть</button>
                    </form>

                    @if($selectedTable)
                        <p class="text-muted mb-2">
                            Таблица: <strong>{{ $selectedTable }}</strong>
                            @if($pagination)
                                — всего {{ number_format($pagination['total'], 0, '.', ' ') }} записей,
                                страница {{ $pagination['page'] }} / {{ $pagination['last_page'] }}
                            @endif
                        </p>

                        <div class="table-responsive" style="max-height:70vh;">
                            <table class="table table-bordered table-striped table-sm pg-data-table">
                                <thead class="thead-light" style="position:sticky; top:0; z-index:1;">
                                    <tr>
                                        @foreach($columns as $col)
                                            <th>
                                                {{ $col }}
                                                @if(!empty($columnMeta[$col]['is_vector']))
                                                    <span class="badge badge-info">vector</span>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rows as $row)
                                        <tr>
                                            @foreach($columns as $col)
                                                @php
                                                    $val = $row[$col] ?? null;
                                                    $isVector = !empty($columnMeta[$col]['is_vector']);
                                                    $full = $val === null ? '' : (string) $val;
                                                    $needTruncate = $isVector || mb_strlen($full) > 100;
                                                @endphp
                                                <td style="max-width:280px; vertical-align:top;">
                                                    @if($val === null)
                                                        <span class="text-muted">NULL</span>
                                                    @elseif($needTruncate)
                                                        <span class="pg-cell-preview text-monospace" style="cursor:pointer; color:#007bff;"
                                                              title="Клик — показать полностью"
                                                              data-full="{{ $full }}">
                                                            {{ $isVector ? '[vector] '.mb_substr($full, 0, 60).'…' : \App\Http\Controllers\Admin\PostgresManagerController::truncatePreview($full) }}
                                                        </span>
                                                    @else
                                                        <span class="text-monospace">{{ $full }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(1, count($columns)) }}" class="text-center text-muted">Нет данных</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($pagination && $pagination['last_page'] > 1)
                            <nav class="mt-3">
                                <ul class="pagination pagination-sm mb-0">
                                    @for($p = 1; $p <= $pagination['last_page']; $p++)
                                        <li class="page-item {{ $p === $pagination['page'] ? 'active' : '' }}">
                                            <a class="page-link"
                                               href="{{ route('admin.postgres-manager.index', ['tab' => 'viewer', 'table' => $selectedTable, 'page' => $p]) }}">
                                                {{ $p }}
                                            </a>
                                        </li>
                                    @endfor
                                </ul>
                            </nav>
                        @endif
                    @else
                        <p class="text-muted">Выберите таблицу.</p>
                    @endif
                @endif

                @if($tab === 'console')
                    <form method="post" action="{{ route('admin.postgres-manager.query') }}">
                        @csrf
                        <div class="form-group">
                            <label for="sql">SQL</label>
                            <textarea name="sql" id="sql" rows="10" class="form-control pg-sql-input"
                                      placeholder="SELECT * FROM sw_calc_pages LIMIT 20;"
                                      required>{{ old('sql', $sqlInput ?? '') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted mr-2">Шаблоны:</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1" id="pg-tpl-vector-tables">
                                Показать векторные таблицы
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1" id="pg-tpl-vector-search">
                                Тест векторного поиска
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger mr-1 mb-1" id="pg-tpl-clear-logs">
                                Очистить логи sw_calc_logs
                            </button>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play"></i> Выполнить
                        </button>
                    </form>

                    @if($sqlResult)
                        <hr>
                        <h5>Результат</h5>
                        @if(!($sqlResult['ok'] ?? false))
                            <div class="alert alert-danger">
                                <strong>PDO Error:</strong> {{ $sqlResult['message'] ?? 'Unknown error' }}
                            </div>
                        @elseif(($sqlResult['type'] ?? '') === 'select')
                            <p class="text-muted">Строк: {{ count($sqlResult['rows'] ?? []) }}</p>
                            <div class="table-responsive" style="max-height:60vh;">
                                <table class="table table-bordered table-sm table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            @foreach(($sqlResult['columns'] ?? []) as $col)
                                                <th>{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($sqlResult['rows'] ?? []) as $row)
                                            <tr>
                                                @foreach(($sqlResult['columns'] ?? []) as $col)
                                                    @php
                                                        $val = $row[$col] ?? null;
                                                        $full = $val === null ? '' : (string) $val;
                                                        $needTruncate = mb_strlen($full) > 100;
                                                    @endphp
                                                    <td style="max-width:280px; vertical-align:top;">
                                                        @if($val === null)
                                                            <span class="text-muted">NULL</span>
                                                        @elseif($needTruncate)
                                                            <span class="pg-cell-preview text-monospace" style="cursor:pointer; color:#007bff;"
                                                                  data-full="{{ $full }}">
                                                                {{ \App\Http\Controllers\Admin\PostgresManagerController::truncatePreview($full) }}
                                                            </span>
                                                        @else
                                                            <span class="text-monospace">{{ $full }}</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="text-muted">Пустой результат</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-success">
                                {{ $sqlResult['message'] ?? 'OK' }}
                                — rows affected: <strong>{{ $sqlResult['affected'] ?? 0 }}</strong>
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>

{{-- Modal for full cell value --}}
<div class="modal fade" id="pg-cell-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Полное значение</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="pg-cell-modal-body" class="mb-0" style="white-space:pre-wrap; word-break:break-word; max-height:70vh; overflow:auto;"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
.pg-sql-input {
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
    border: 1px solid #333;
}
.pg-sql-input:focus {
    background: #1e1e1e;
    color: #fff;
    border-color: #007bff;
    box-shadow: none;
}
.pg-data-table td, .pg-data-table th {
    white-space: nowrap;
}
</style>
<script>
(function ($) {
    $(function () {
        $(document).on('click', '.pg-cell-preview', function () {
            var full = $(this).attr('data-full') || '';
            $('#pg-cell-modal-body').text(full);
            $('#pg-cell-modal').modal('show');
        });

        $('#pg-tpl-vector-tables').on('click', function () {
            $('#sql').val(
                "SELECT c.relname AS table_name, a.attname AS column_name, format_type(a.atttypid, a.atttypmod) AS data_type\n" +
                "FROM pg_attribute a\n" +
                "JOIN pg_class c ON a.attrelid = c.oid\n" +
                "JOIN pg_namespace n ON c.relnamespace = n.oid\n" +
                "WHERE n.nspname = 'public'\n" +
                "  AND NOT a.attisdropped\n" +
                "  AND a.attnum > 0\n" +
                "  AND format_type(a.atttypid, a.atttypmod) LIKE 'vector%'\n" +
                "ORDER BY c.relname, a.attnum;"
            );
        });

        $('#pg-tpl-vector-search').on('click', function () {
            $('#sql').val(
                "SELECT id, content, embedding <=> (SELECT embedding FROM sw_calc_chunks LIMIT 1) AS distance\n" +
                "FROM sw_calc_chunks\n" +
                "WHERE embedding IS NOT NULL\n" +
                "ORDER BY embedding <=> (SELECT embedding FROM sw_calc_chunks LIMIT 1)\n" +
                "LIMIT 5;"
            );
        });

        $('#pg-tpl-clear-logs').on('click', function () {
            $('#sql').val('TRUNCATE TABLE sw_calc_logs RESTART IDENTITY;');
        });
    });
})(jQuery);
</script>
@endsection
