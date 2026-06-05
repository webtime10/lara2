<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryDescription;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $pageTitle = 'Categories - Список';
        $defaultLanguage = Language::getDefault();
        $categories = Category::with(['parent.descriptions', 'descriptions'])
            ->orderBy('sort_order')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('admin.categories.index', compact('categories', 'pageTitle', 'defaultLanguage'));
    }

    public function create()
    {
        $pageTitle = 'Categories - Создание';
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $parentOptions = Category::treeForParentSelect($defaultLanguage, []);

        return view('admin.categories.create', compact('pageTitle', 'parentOptions', 'languages', 'defaultLanguage'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
        ]);

        $languages = Language::forAdminForms();
        if ($languages->isEmpty()) {
            return redirect()->route('admin.languages.index')
                ->with('info', 'Добавьте хотя бы один язык — после этого в формах появятся поля названий.');
        }

        $rules = [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ];

        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
            $rules['meta_title_'.$suffix] = 'nullable|string|max:255';
            $rules['meta_description_'.$suffix] = 'nullable|string|max:255';
            $rules['meta_keyword_'.$suffix] = 'nullable|string|max:255';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages) {
            $category = Category::create([
                'parent_id' => $request->input('parent_id'),
                'image' => null,
                'top' => false,
                'column' => 0,
                'sort_order' => (int) $request->input('sort_order', 0),
                'status' => $request->boolean('status'),
            ]);

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if (! $language->is_default && $name === '') {
                    continue;
                }

                CategoryDescription::create([
                    'category_id' => $category->id,
                    'language_id' => $language->id,
                    'name' => $name,
                    'description' => $request->input('description_'.$suffix),
                    'meta_title' => $request->input('meta_title_'.$suffix),
                    'meta_description' => $request->input('meta_description_'.$suffix),
                    'meta_keyword' => $request->input('meta_keyword_'.$suffix),
                ]);
            }

            Category::rebuildPaths();
        });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно создана');
    }

    public function edit(string $id)
    {
        $pageTitle = 'Categories - Редактирование';
        $category = Category::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $excludeIds = array_merge([(int) $category->id], $category->descendantIdList());
        $parentOptions = Category::treeForParentSelect($defaultLanguage, $excludeIds);

        return view('admin.categories.edit', compact('category', 'pageTitle', 'parentOptions', 'languages', 'defaultLanguage'));
    }

    public function update(Request $request, string $id)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
        ]);

        $category = Category::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();
        if ($languages->isEmpty()) {
            return redirect()->route('admin.languages.index')
                ->with('info', 'Добавьте хотя бы один язык — после этого в формах появятся поля названий.');
        }

        $rules = [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn(array_merge([(int) $category->id], $category->descendantIdList())),
            ],
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ];

        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
            $rules['meta_title_'.$suffix] = 'nullable|string|max:255';
            $rules['meta_description_'.$suffix] = 'nullable|string|max:255';
            $rules['meta_keyword_'.$suffix] = 'nullable|string|max:255';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages, $category) {
            $category->update([
                'parent_id' => $request->input('parent_id'),
                'top' => false,
                'column' => 0,
                'sort_order' => (int) $request->input('sort_order', 0),
                'status' => $request->boolean('status'),
            ]);

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if (! $language->is_default && $name === '') {
                    CategoryDescription::query()
                        ->where('category_id', $category->id)
                        ->where('language_id', $language->id)
                        ->delete();

                    continue;
                }

                CategoryDescription::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'language_id' => $language->id,
                    ],
                    [
                        'name' => $name,
                        'description' => $request->input('description_'.$suffix),
                        'meta_title' => $request->input('meta_title_'.$suffix),
                        'meta_description' => $request->input('meta_description_'.$suffix),
                        'meta_keyword' => $request->input('meta_keyword_'.$suffix),
                    ]
                );
            }

            Category::rebuildPaths();
        });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно обновлена');
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        Category::rebuildPaths();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Категория успешно удалена');
    }
}
