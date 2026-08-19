<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileManagerController extends Controller
{
    private string $uploadDir;
    private string $uploadUrl;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct()
    {
        $this->uploadDir = public_path('uploads/catalog');
        $this->uploadUrl = '/uploads/catalog';
    }

    /** Встроенное модальное окно — рендерится в layout страницы */
    public function modal(Request $request)
    {
        return view('admin.filemanager.modal', [
            'target' => $request->get('target', ''),
            'thumb'  => $request->get('thumb', ''),
        ]);
    }

    /** AJAX: список файлов — подгружается внутрь модального окна */
    public function list(Request $request)
    {
        if (! is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $search = (string) $request->get('search', '');
        $files  = [];

        foreach (glob($this->uploadDir . '/*') as $file) {
            if (! is_file($file)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, $this->allowedExtensions)) {
                continue;
            }
            $name = basename($file);
            if ($search && stripos($name, $search) === false) {
                continue;
            }
            $files[] = [
                'name'  => $name,
                'url'   => $this->uploadUrl . '/' . $name,
                'mtime' => filemtime($file),
            ];
        }

        usort($files, fn ($a, $b) => $b['mtime'] - $a['mtime']);

        return view('admin.filemanager.list', compact('files'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:4096',
        ]);

        if (! is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $file = $request->file('file');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            . '_' . time()
            . '.' . strtolower($file->getClientOriginalExtension());

        $file->move($this->uploadDir, $name);

        return response()->json([
            'success' => true,
            'url'     => $this->uploadUrl . '/' . $name,
            'name'    => $name,
        ]);
    }

    public function delete(Request $request)
    {
        $name = basename((string) $request->input('name', ''));
        if ($name) {
            $path = $this->uploadDir . '/' . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }

        return response()->json(['success' => true]);
    }
}
