<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\ProductStatusRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\ProductStatus;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('products.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return $this->form(new Product);
    }

    public function store(ProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->save($request->validated());

        return redirect()->route('products.show', $product)->with('status', 'Product created.');
    }

    public function show(Product $product): View
    {
        Gate::authorize('view', $product);
        $product->load(['stockItem', 'category', 'brand', 'unit']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return $this->form($product->load('stockItem'));
    }

    public function update(ProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->save($request->validated(), $product);

        return redirect()->route('products.show', $product)->with('status', 'Product updated.');
    }

    public function status(ProductStatusRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->changeStatus($product, ProductStatus::from($request->validated('status')));

        return redirect()->route('products.show', $product)->with('status', 'Product status updated.');
    }

    private function form(Product $product): View
    {
        $options = [];
        foreach (['categories' => [Category::class, 'category_id'], 'brands' => [Brand::class, 'brand_id'], 'units' => [Unit::class, 'unit_id']] as $key => [$model, $field]) {
            $options[$key] = $model::query()->where(function (Builder $query) use ($product, $field): void {
                $query->where('status', 'active');
                if ($product->{$field}) {
                    $query->orWhere('id', $product->{$field});
                }
            })->orderBy('name')->get();
        }

        return view('products.form', ['product' => $product, ...$options]);
    }
}
