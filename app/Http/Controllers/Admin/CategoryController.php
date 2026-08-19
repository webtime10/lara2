<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryDescription;
use App\Models\Language;
use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $pageTitle = 'Категории';
        $defaultLanguage = Language::getDefault();
        $categories = Category::with(['parent.descriptions', 'descriptions', 'manufacturer'])
            ->orderBy('sort_order')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.categories.index', compact('categories', 'pageTitle', 'defaultLanguage'));
    }

    public function create()
    {
        $pageTitle = 'Категории — новый регион';
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $parentOptions = Category::treeForParentSelect($defaultLanguage, []);
        $manufacturers = Manufacturer::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.categories.create', compact('pageTitle', 'parentOptions', 'languages', 'defaultLanguage', 'manufacturers'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
            'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null,
        ]);

        $languages = Language::forAdminForms();
        if ($languages->isEmpty()) {
            return redirect()->route('admin.languages.index')
                ->with('info', 'Добавьте хотя бы один язык — после этого в формах появятся поля названий.');
        }

        $rules = [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'manufacturer_id' => ['required', 'integer', 'exists:manufacturers,id'],
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ];

        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
            $rules = array_merge($rules, $this->idealRegionFieldRules($suffix));
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages) {
            $category = Category::create([
                'parent_id' => $request->input('parent_id'),
                'manufacturer_id' => $request->input('manufacturer_id'),
                'image' => $request->input('image') ?: null,
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

                CategoryDescription::create(array_merge([
                    'category_id' => $category->id,
                    'language_id' => $language->id,
                    'name' => $name,
                    'slug' => CategoryDescription::uniqueSlugForLanguage($name, (int) $language->id),
                    'description' => $request->input('description_'.$suffix),
                    'meta_title' => null,
                    'meta_description' => null,
                    'meta_keyword' => null,
                ], $this->idealRegionFieldValues($request, $suffix)));
            }

            Category::rebuildPaths();
        });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Регион успешно создан');
    }

    public function edit(string $id)
    {
        $pageTitle = 'Категории — редактирование региона';
        $category = Category::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $excludeIds = array_merge([(int) $category->id], $category->descendantIdList());
        $parentOptions = Category::treeForParentSelect($defaultLanguage, $excludeIds);
        $manufacturers = Manufacturer::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.categories.edit', compact('category', 'pageTitle', 'parentOptions', 'languages', 'defaultLanguage', 'manufacturers'));
    }

    public function update(Request $request, string $id)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
            'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null,
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
            'manufacturer_id' => ['required', 'integer', 'exists:manufacturers,id'],
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ];

        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
            $rules = array_merge($rules, $this->idealRegionFieldRules($suffix));
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages, $category) {
            $category->update([
                'parent_id' => $request->input('parent_id'),
                'manufacturer_id' => $request->input('manufacturer_id'),
                'image' => $request->input('image') ?: null,
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
                    array_merge([
                        'name' => $name,
                        'slug' => CategoryDescription::uniqueSlugForLanguage(
                            $name,
                            (int) $language->id,
                            (int) $category->id
                        ),
                        'description' => $request->input('description_'.$suffix),
                        'meta_title' => null,
                        'meta_description' => null,
                        'meta_keyword' => null,
                    ], $this->idealRegionFieldValues($request, $suffix))
                );
            }

            Category::rebuildPaths();
        });

        return redirect()->route('admin.categories.index')
            ->with('success', 'Регион успешно обновлён');
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        Category::rebuildPaths();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Регион успешно удалён');
    }

    public function bulkDelete(Request $request)
    {
        $ids = collect((array) $request->input('selected', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->route('admin.categories.index')
                ->with('error', 'Выберите регионы для удаления.');
        }

        Category::query()->whereIn('id', $ids)->delete();
        Category::rebuildPaths();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Удалено регионов: '.$ids->count());
    }

    /**
     * @return list<string>
     */
    private function idealRegionFields(): array
    {
        $config = (array) config('ideal_region_category_fields', []);
        $fields = $config['fields'] ?? $config;

        return array_values(array_filter(
            (array) $fields,
            fn ($field) => is_string($field) && str_starts_with($field, 'step')
        ));
    }

    /**
     * @return array<string, string>
     */
    private function idealRegionFieldRules(string $suffix): array
    {
        $rules = [];
        foreach ($this->idealRegionFields() as $field) {
            if (str_ends_with($field, '_description')) {
                $rules[$field.'_'.$suffix] = 'nullable|string';
            } else {
                $rules[$field.'_'.$suffix] = 'nullable|string|max:255';
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string|null>
     */
    private function idealRegionFieldValues(Request $request, string $suffix): array
    {
        $values = [];
        foreach ($this->idealRegionFields() as $field) {
            $raw = $request->input($field.'_'.$suffix);
            $values[$field] = is_string($raw) ? $raw : null;
        }

        return $values;
    }
}
