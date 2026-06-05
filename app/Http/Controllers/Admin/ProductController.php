<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $pageTitle = 'Товары';
        $defaultLanguage = Language::getDefault();
        $products = Product::query()
            ->with(['descriptions' => fn ($q) => $q->where('language_id', $defaultLanguage?->id), 'manufacturer'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.products.index', compact('products', 'pageTitle', 'defaultLanguage'));
    }

    public function create()
    {
        $pageTitle = 'Товар — создание';
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $manufacturers = Manufacturer::orderBy('sort_order')->orderBy('name')->get();
        $categories = Category::with('descriptions')->orderBy('sort_order')->get();

        return view('admin.products.create', compact('pageTitle', 'languages', 'defaultLanguage', 'manufacturers', 'categories'));
    }

    public function store(Request $request)
    {
        $languages = Language::forAdminForms();
        if ($languages->isEmpty()) {
            return redirect()->route('admin.languages.index')
                ->with('info', 'Добавьте хотя бы один язык — после этого в формах появятся поля названий.');
        }

        $rules = [
            'model' => 'required|string|max:64',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'status' => 'nullable|boolean',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
        ];
        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages) {
            $product = Product::create([
                'model' => $request->model,
                'sku' => $request->input('sku'),
                'image' => null,
                'manufacturer_id' => $request->input('manufacturer_id'),
                'shipping' => true,
                'price' => 0,
                'quantity' => 0,
                'subtract' => true,
                'minimum' => 1,
                'sort_order' => 0,
                'status' => $request->boolean('status'),
            ]);

            $product->categories()->sync($request->category_ids);

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if (! $language->is_default && $name === '') {
                    continue;
                }

                ProductDescription::create([
                    'product_id' => $product->id,
                    'language_id' => $language->id,
                    'name' => $name,
                    'description' => $request->input('description_'.$suffix),
                    'tag' => $request->input('tag_'.$suffix),
                    'meta_title' => $request->input('meta_title_'.$suffix),
                    'meta_description' => $request->input('meta_description_'.$suffix),
                    'meta_keyword' => $request->input('meta_keyword_'.$suffix),
                ]);
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Товар создан');
    }

    public function edit(string $id)
    {
        $pageTitle = 'Товар — редактирование';
        $product = Product::with(['descriptions', 'categories'])->findOrFail($id);
        $languages = Language::forAdminForms();
        $defaultLanguage = Language::getDefault();
        $manufacturers = Manufacturer::orderBy('sort_order')->orderBy('name')->get();
        $categories = Category::with('descriptions')->orderBy('sort_order')->get();

        return view('admin.products.edit', compact('product', 'pageTitle', 'languages', 'defaultLanguage', 'manufacturers', 'categories'));
    }

    public function update(Request $request, string $id)
    {
        $product = Product::with('descriptions')->findOrFail($id);
        $languages = Language::forAdminForms();
        if ($languages->isEmpty()) {
            return redirect()->route('admin.languages.index')
                ->with('info', 'Добавьте хотя бы один язык — после этого в формах появятся поля названий.');
        }

        $rules = [
            'model' => 'required|string|max:64',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'status' => 'nullable|boolean',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
        ];
        foreach ($languages as $language) {
            $suffix = $language->code;
            $rules['name_'.$suffix] = $language->is_default ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules['description_'.$suffix] = 'nullable|string';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $languages, $product) {
            $product->update([
                'model' => $request->model,
                'sku' => $request->input('sku'),
                'manufacturer_id' => $request->input('manufacturer_id'),
                'status' => $request->boolean('status'),
            ]);
            $product->categories()->sync($request->category_ids);

            foreach ($languages as $language) {
                $suffix = $language->code;
                $name = trim((string) $request->input('name_'.$suffix, ''));
                if (! $language->is_default && $name === '') {
                    ProductDescription::query()
                        ->where('product_id', $product->id)
                        ->where('language_id', $language->id)
                        ->delete();
                    continue;
                }

                ProductDescription::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'language_id' => $language->id,
                    ],
                    [
                        'name' => $name,
                        'description' => $request->input('description_'.$suffix),
                        'tag' => $request->input('tag_'.$suffix),
                        'meta_title' => $request->input('meta_title_'.$suffix),
                        'meta_description' => $request->input('meta_description_'.$suffix),
                        'meta_keyword' => $request->input('meta_keyword_'.$suffix),
                    ]
                );
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Товар обновлён');
    }

    public function destroy(string $id)
    {
        Product::findOrFail($id)->delete();

        return redirect()->route('admin.products.index')->with('success', 'Товар удалён');
    }
}
