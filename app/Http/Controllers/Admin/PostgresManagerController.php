<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostgresManagerController extends Controller
{
    private const PER_PAGE = 20;

    private const TRUNCATE_AT = 100;

    public function index(Request $request)
    {
        $pageTitle = 'PostgreSQL Manager';
        $tab = $request->get('tab', 'dashboard');
        if (! in_array($tab, ['dashboard', 'viewer', 'console'], true)) {
            $tab = 'dashboard';
        }

        $connectionError = null;
        $tables = [];
        $pgvector = ['installed' => false, 'version' => null];
        $selectedTable = null;
        $columns = [];
        $columnMeta = [];
        $rows = [];
        $pagination = null;
        $sqlResult = null;

        try {
            $tables = $this->listTables();
            $pgvector = $this->pgvectorStatus();
            $tableCounts = $this->tableCounts($tables);
        } catch (Throwable $e) {
            $connectionError = $e->getMessage();
            $tableCounts = [];
        }

        if ($connectionError === null && $tab === 'viewer') {
            $selectedTable = (string) $request->get('table', '');
            if ($selectedTable !== '' && in_array($selectedTable, $tables, true)) {
                $page = max(1, (int) $request->get('page', 1));
                $viewer = $this->fetchTableData($selectedTable, $page);
                $columns = $viewer['columns'];
                $columnMeta = $viewer['column_meta'];
                $rows = $viewer['rows'];
                $pagination = $viewer['pagination'];
            } elseif ($selectedTable === '' && ! empty($tables)) {
                $selectedTable = $tables[0];
                $page = max(1, (int) $request->get('page', 1));
                $viewer = $this->fetchTableData($selectedTable, $page);
                $columns = $viewer['columns'];
                $columnMeta = $viewer['column_meta'];
                $rows = $viewer['rows'];
                $pagination = $viewer['pagination'];
            }
        }

        return view('admin.postgres-manager.index', compact(
            'pageTitle',
            'tab',
            'connectionError',
            'tables',
            'tableCounts',
            'pgvector',
            'selectedTable',
            'columns',
            'columnMeta',
            'rows',
            'pagination',
            'sqlResult'
        ));
    }

    public function query(Request $request)
    {
        $request->validate([
            'sql' => 'required|string|max:20000',
        ]);

        $sql = trim((string) $request->input('sql'));
        $pageTitle = 'PostgreSQL Manager';
        $tab = 'console';
        $connectionError = null;
        $tables = [];
        $tableCounts = [];
        $pgvector = ['installed' => false, 'version' => null];
        $selectedTable = null;
        $columns = [];
        $columnMeta = [];
        $rows = [];
        $pagination = null;
        $sqlResult = null;

        try {
            $tables = $this->listTables();
            $tableCounts = $this->tableCounts($tables);
            $pgvector = $this->pgvectorStatus();
            $sqlResult = $this->runSql($sql);
        } catch (Throwable $e) {
            $sqlResult = [
                'ok' => false,
                'type' => 'error',
                'message' => $e->getMessage(),
                'columns' => [],
                'rows' => [],
                'affected' => null,
            ];
            try {
                $tables = $this->listTables();
                $tableCounts = $this->tableCounts($tables);
                $pgvector = $this->pgvectorStatus();
            } catch (Throwable $ignore) {
                $connectionError = $ignore->getMessage();
            }
        }

        return view('admin.postgres-manager.index', compact(
            'pageTitle',
            'tab',
            'connectionError',
            'tables',
            'tableCounts',
            'pgvector',
            'selectedTable',
            'columns',
            'columnMeta',
            'rows',
            'pagination',
            'sqlResult'
        ))->with('sqlInput', $sql);
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function tableCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $quoted = $this->quoteIdent($table);
            $counts[$table] = (int) DB::connection('pgsql')
                ->selectOne("SELECT COUNT(*) AS c FROM {$quoted}")
                ->c;
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function listTables(): array
    {
        $rows = DB::connection('pgsql')->select(
            "SELECT tablename
             FROM pg_tables
             WHERE schemaname = 'public'
             ORDER BY tablename"
        );

        return array_values(array_map(
            static fn ($row) => (string) $row->tablename,
            $rows
        ));
    }

    /**
     * @return array{installed: bool, version: string|null}
     */
    private function pgvectorStatus(): array
    {
        $row = DB::connection('pgsql')->selectOne(
            "SELECT extversion
             FROM pg_extension
             WHERE extname = 'vector'
             LIMIT 1"
        );

        if ($row && isset($row->extversion)) {
            return [
                'installed' => true,
                'version' => (string) $row->extversion,
            ];
        }

        return [
            'installed' => false,
            'version' => null,
        ];
    }

    /**
     * @return array{
     *   columns: list<string>,
     *   column_meta: array<string, array{udt: string, is_vector: bool}>,
     *   rows: list<array<string, mixed>>,
     *   pagination: array{page: int, per_page: int, total: int, last_page: int}
     * }
     */
    private function fetchTableData(string $table, int $page): array
    {
        $metaRows = DB::connection('pgsql')->select(
            "SELECT column_name, udt_name, data_type
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        );

        $columns = [];
        $columnMeta = [];
        foreach ($metaRows as $meta) {
            $name = (string) $meta->column_name;
            $udt = strtolower((string) $meta->udt_name);
            $columns[] = $name;
            $columnMeta[$name] = [
                'udt' => $udt,
                'is_vector' => $udt === 'vector',
            ];
        }

        $quoted = $this->quoteIdent($table);
        $total = (int) DB::connection('pgsql')->selectOne("SELECT COUNT(*) AS c FROM {$quoted}")->c;
        $perPage = self::PER_PAGE;
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $rawRows = DB::connection('pgsql')->select(
            "SELECT * FROM {$quoted} ORDER BY 1 LIMIT {$perPage} OFFSET {$offset}"
        );

        $rows = [];
        foreach ($rawRows as $raw) {
            $item = [];
            foreach ($columns as $col) {
                $item[$col] = $this->normalizeCellValue($raw->$col ?? null);
            }
            $rows[] = $item;
        }

        return [
            'columns' => $columns,
            'column_meta' => $columnMeta,
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   type: string,
     *   message?: string,
     *   columns: list<string>,
     *   rows: list<array<string, mixed>>,
     *   affected: int|null
     * }
     */
    private function runSql(string $sql): array
    {
        $conn = DB::connection('pgsql');
        $trimmed = ltrim($sql);
        $isSelectLike = (bool) preg_match('/^(SELECT|WITH|EXPLAIN|SHOW|VALUES)\b/i', $trimmed);

        if ($isSelectLike) {
            $rawRows = $conn->select($sql);
            $columns = [];
            $rows = [];

            if (! empty($rawRows)) {
                $columns = array_keys((array) $rawRows[0]);
                foreach ($rawRows as $raw) {
                    $item = [];
                    foreach ($columns as $col) {
                        $item[$col] = $this->normalizeCellValue($raw->$col ?? null);
                    }
                    $rows[] = $item;
                }
            }

            return [
                'ok' => true,
                'type' => 'select',
                'columns' => $columns,
                'rows' => $rows,
                'affected' => count($rows),
            ];
        }

        $affected = $conn->affectingStatement($sql);

        return [
            'ok' => true,
            'type' => 'statement',
            'columns' => [],
            'rows' => [],
            'affected' => $affected,
            'message' => 'Query executed successfully.',
        ];
    }

    private function quoteIdent(string $ident): string
    {
        return '"'.str_replace('"', '""', $ident).'"';
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    /**
     * Used in Blade for truncated display.
     */
    public static function shouldTruncate(mixed $value, bool $isVector = false): bool
    {
        if ($isVector) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        return mb_strlen((string) $value) > self::TRUNCATE_AT;
    }

    public static function truncatePreview(mixed $value): string
    {
        $str = (string) $value;

        return mb_substr($str, 0, self::TRUNCATE_AT).'…';
    }
}
