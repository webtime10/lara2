<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;
use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JCategoryController extends Controller
{
    /** Основной язык контента Японии в админке. */
    private const CONTENT_LANG = 'he';

    public function index()
    {
        $pageTitle = 'Категории (Япония)';
        $contentLanguage = $this->contentLanguage();
        $categories = JCategory::with(['parent.descriptions', 'descriptions', 'manufacturer'])
            ->orderBy('sort_order')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.j-categories.index', [
            'categories' => $categories,
            'pageTitle' => $pageTitle,
            'defaultLanguage' => $contentLanguage,
        ]);
    }

    public function create()
    {
        $pageTitle = 'Категории (Япония) — новый регион';
        $languages = Language::forAdminForms();
        $contentLanguage = $this->contentLanguage();
        $parentOptions = JCategory::treeForParentSelect($contentLanguage, []);
        $manufacturers = Manufacturer::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.j-categories.create', [
            'pageTitle' => $pageTitle,
            'parentOptions' => $parentOptions,
            'languages' => $languages,
            'defaultLanguage' => $contentLanguage,
            'contentLangCode' => self::CONTENT_LANG,
            'manufacturers' => $manufacturers,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
            'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null,
        ]);

        $languages = Language::forAdminForms();

        DB::transaction(function () use ($request, $languages) {
            $category = JCategory::create([
                'parent_id' => $request->input('parent_id'),
                'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->input('manufacturer_id') : null,
                'tourist_region' => $request->input('tourist_region') ?: null,
                'image' => null,
                'top' => false,
                'column' => 0,
                'sort_order' => (int) $request->input('sort_order', 0),
                'status' => $request->boolean('status'),
            ]);

            $fallbackImage = null;

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if ($name === '') {
                    continue;
                }

                $langImage = $request->input('image_'.$suffix) ?: null;
                if ($langImage && $fallbackImage === null) {
                    $fallbackImage = $langImage;
                }

                JCategoryDescription::create(array_merge([
                    'j_category_id' => $category->id,
                    'language_id' => $language->id,
                    'name' => $name,
                    'slug' => JCategoryDescription::uniqueSlugForLanguage($name, (int) $language->id),
                    'image' => $langImage,
                    'description' => $request->input('description_'.$suffix),
                    'meta_title' => null,
                    'meta_description' => null,
                    'meta_keyword' => null,
                ], $this->idealRegionFieldValues($request, $suffix)));
            }

            // Для списка/совместимости: картинка he или первая заполненная.
            $heImage = $request->input('image_'.self::CONTENT_LANG) ?: $fallbackImage;
            if ($heImage) {
                $category->update(['image' => $heImage]);
            }

            JCategory::rebuildPaths();
        });

        return redirect()->route('admin.j-categories.index')
            ->with('success', 'Регион (Япония) успешно создан');
    }

    public function edit(string $id)
    {
        $pageTitle = 'Категории (Япония) — редактирование';
        $category = JCategory::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();
        $contentLanguage = $this->contentLanguage();
        $excludeIds = array_merge([(int) $category->id], $category->descendantIdList());
        $parentOptions = JCategory::treeForParentSelect($contentLanguage, $excludeIds);
        $manufacturers = Manufacturer::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.j-categories.edit', [
            'category' => $category,
            'pageTitle' => $pageTitle,
            'parentOptions' => $parentOptions,
            'languages' => $languages,
            'defaultLanguage' => $contentLanguage,
            'contentLangCode' => self::CONTENT_LANG,
            'manufacturers' => $manufacturers,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $request->merge([
            'parent_id' => $request->filled('parent_id') ? (int) $request->parent_id : null,
            'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->manufacturer_id : null,
        ]);

        $category = JCategory::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();

        DB::transaction(function () use ($request, $languages, $category) {
            $category->update([
                'parent_id' => $request->input('parent_id'),
                'manufacturer_id' => $request->filled('manufacturer_id') ? (int) $request->input('manufacturer_id') : null,
                'tourist_region' => $request->input('tourist_region') ?: null,
                'top' => false,
                'column' => 0,
                'sort_order' => (int) $request->input('sort_order', 0),
                'status' => $request->boolean('status'),
            ]);

            $fallbackImage = null;

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if ($name === '') {
                    JCategoryDescription::query()
                        ->where('j_category_id', $category->id)
                        ->where('language_id', $language->id)
                        ->delete();

                    continue;
                }

                $langImage = $request->input('image_'.$suffix) ?: null;
                if ($langImage && $fallbackImage === null) {
                    $fallbackImage = $langImage;
                }

                JCategoryDescription::updateOrCreate(
                    [
                        'j_category_id' => $category->id,
                        'language_id' => $language->id,
                    ],
                    array_merge([
                        'name' => $name,
                        'slug' => JCategoryDescription::uniqueSlugForLanguage(
                            $name,
                            (int) $language->id,
                            (int) $category->id
                        ),
                        'image' => $langImage,
                        'description' => $request->input('description_'.$suffix),
                        'meta_title' => null,
                        'meta_description' => null,
                        'meta_keyword' => null,
                    ], $this->idealRegionFieldValues($request, $suffix))
                );
            }

            $heImage = $request->input('image_'.self::CONTENT_LANG) ?: $fallbackImage;
            $category->update(['image' => $heImage ?: null]);

            JCategory::rebuildPaths();
        });

        return redirect()->route('admin.j-categories.index')
            ->with('success', 'Регион (Япония) успешно обновлён');
    }

    public function destroy(string $id)
    {
        $category = JCategory::findOrFail($id);
        $category->delete();
        JCategory::rebuildPaths();

        return redirect()->route('admin.j-categories.index')
            ->with('success', 'Регион (Япония) успешно удалён');
    }

    public function bulkDelete(Request $request)
    {
        $ids = collect((array) $request->input('selected', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->route('admin.j-categories.index')
                ->with('error', 'Выберите регионы для удаления.');
        }

        JCategory::query()->whereIn('id', $ids)->delete();
        JCategory::rebuildPaths();

        return redirect()->route('admin.j-categories.index')
            ->with('success', 'Удалено регионов: '.$ids->count());
    }

    /**
     * @return list<string>
     */
    private function idealRegionFields(): array
    {
        $config = (array) config('japan_ideal_region_category_fields', []);
        $fields = $config['fields'] ?? $config;

        return array_values(array_filter(
            (array) $fields,
            fn ($field) => is_string($field) && str_starts_with($field, 'step')
        ));
    }

    private function contentLanguage(): ?Language
    {
        return Language::query()->where('code', self::CONTENT_LANG)->first()
            ?: Language::getDefault();
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
