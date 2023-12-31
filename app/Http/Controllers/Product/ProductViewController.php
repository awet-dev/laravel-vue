<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Color\ColorModelController;
use App\Http\Controllers\Inventory\InventoryModelController;
use App\Http\Controllers\Size\SizeModelController;
use App\Http\Controllers\Variant\VariantModelController;
use App\Http\Controllers\Variation\VariationModelController;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Variant\VariantCollection;
use App\Models\Attributes\Color;
use App\Models\Attributes\Size;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Constants\Ability;
use App\Models\Product;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ProductViewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $closet = $user->currentCloset();

        $products = $closet->products()
            ->when($request->get('search'), static function (Builder $query, string $search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%");
            })
            ->paginate($request->get('per_page', 10))
            ->through(fn($product) => [ // todo user resource
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'desc' => $product->desc,
                'price' => $product->price,
                'created_at' => $product->created_at,
                'can' => [
                    Ability::DELETE => $user->can(Ability::DELETE, $product)
                ]
            ])
            ->withQueryString()
            ->onEachSide(0);

        return Inertia::render('Product/Index', [
            'products' => $products,
            'filters' => $request->only(['search']),
            'can' => [
                Ability::CREATE => $user->can(Ability::CREATE, Product::class),
                Ability::DELETE_ANY => $user->can(Ability::DELETE_ANY, Product::class)
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function details(Product $product): RedirectResponse|Response
    {
        /** @var User $user */
        $user = Auth::user();
        $closet = $user->currentCloset();

        $variants = ProductModelController::getByCloset($product, $closet);

        return Inertia::render('Product/Details', [
            'product' => $product,
            'product_variants' => new VariantCollection($variants),
            'sizes' => Size::all(),
            'colors' => Color::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @throws AuthorizationException
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize(Ability::CREATE, Product::class);

        return is_null($product = Product::create($request->validated()))
            ? back()->withErrors(['error' => 'fail to create product'])
            : Redirect::route('products.details', ['product' => $product->id])->with('message', 'product created');
    }

    /**
     * Show the form for creating a new resource.
     * @throws AuthorizationException
     */
    public function create(): Response
    {
        $this->authorize(Ability::CREATE, Product::class);

        return Inertia::render('Product/Details', [
            'product' => null,
            'product_variants' => null
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @throws AuthorizationException
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize(Ability::UPDATE, $product);

        return $product->update($request->validated())
            ? Redirect::route('products.details', ['product' => $product->id])
                ->with('message', 'product-updated')
            : back()->withErrors(['error' => 'fail to update product']);
    }

    public function addVariant(Product $product, Request $request): RedirectResponse
    {
        $size = SizeModelController::getById($request->get('size_id'));
        $color = ColorModelController::getById($request->get('color_id'));

        if (is_null($size) || is_null($color)) {
            return back()->withErrors(['error' => 'given variations not found']);
        }

        $variant = VariantModelController::create([
            'sku' => $product->sku . '-' . $size->getValue() . '-' . $color->getName(),
            'product_id' => $product->id
        ]);

        /** @var User $user */
        $user = Auth::user();
        $closet = $user->currentCloset();

        InventoryModelController::createForVariant($variant, $closet);

        VariationModelController::createVariationsFor($variant, $color, $size);

        return Redirect::route('products.details', ['product' => $product->id])
            ->with('message', 'variant-created');
    }
}
