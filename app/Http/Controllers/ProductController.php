<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;


class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $allProducts = Product::with('Images')->get();
        $products = Product::with('Images')->where('status', '=', 'published')->paginate($request->input('limit', 10));
        $finalResult = $request->input('limit') ? $products : $allProducts;
        return $finalResult;
    }


    public function getLastSaleProducts(Request $request)
    {
        $products = Product::with('Images')->where('status', '=', 'published')->where('discount', '>', '0')->latest()->take(10)->get();
        return $products;
    }


    public function getLatest(Request $request)
    {
        $products = Product::with('Images')->where('status', '=', 'published')->latest()->take(10)->get();
        return $products;
    }

    public function getTopRated(Request $request)
    {
        $products = Product::with('Images')->where('status', '=', 'published')->where('rating', '>=', '4')->latest()->take(10)->get();
        return $products;
    }


  public function flashDeals()
{
    return Cache::remember('flash_deals_products', 600, function () {
        return Product::with('Images')
            ->where('status', 'published')
            ->where('discount', '>', 0)
            ->latest()
            ->take(10)
            ->get();
    });
}


    public function recommended(Request $request)
{
    $products = Product::with('Images')
        ->where('status', '=', 'published')
        ->where('rating', '>=', 4)
        ->inRandomOrder()
        ->take(10)
        ->get();

    return $products;
}




    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $product = new Product();
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'price' => 'required | numeric',
            'discount' => 'required | numeric',
            'About' => 'required',
            'stock' => 'required | numeric'
        ]);
        $productCreated = $product->create([
            'category' => $request->category,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'About' => $request->About,
            'discount' => $request->discount,
            'stock' => $request->stock

        ]);
        return $productCreated;
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        return Product::where('id', $id)->with('Images')->where('status', '=', 'published')->get();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate([
            'category' => 'required',
            'title' => 'required',
            'description' => 'required',
            'price' => 'required | numeric',
            'discount' => 'required | numeric',
            'stock' => 'required | numeric',
            'About' => 'required'
        ]);
        $product->update([
            'category' => $request->category,
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'About' => $request->About,
            'discount' => $request->discount,
            'stock' => $request->stock

        ]);
        $product->status = 'published';
        $product->save();
        $productId = $product->id;
        if ($request->hasFile('images')) {
            $files = $request->file("images");
            $i = 0;
            foreach ($files as $file) {
                $i = $i + 1;
                $image = new ProductImage();
                $image->product_id = $productId;
                $filename = date('YmdHis') . $i . '.' . $file->getClientOriginalExtension();
                $path = 'images';
                $file->move($path, $filename);
                $image->image = url('/') . '/images/' . $filename;
                $image->save();
            }
        }
    }

    // Search On Users
    public function search(Request $request)
    {
        $query = $request->input('title');
        $results = Product::with('Images')->where('title', 'like', "%$query%")->get();
        return response()->json($results);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $productImages = ProductImage::where('product_id', '=', $id)->get();
        foreach ($productImages as $productImage) {
            $path = public_path() . '/images/' . substr($productImage['image'], strrpos($productImage['image'], '/') + 1);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
        DB::table('products')->where('id', '=', $id)->delete();
    }
// 
public function productsearch(Request $request)
{
    $query = Product::with('Images')
        ->where('status', 'published'); // only published products

    // 🔍 search by keyword (title or description)
    if ($request->filled('q')) {
        $search = $request->q;
        $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%");
        });
    }

    // 🏷 filter by category
    if ($request->filled('category')) {
        $query->where('category', $request->category);
    }

    // 💰 price range
    if ($request->filled('min_price')) {
        $query->where('price', '>=', $request->min_price);
    }

    if ($request->filled('max_price')) {
        $query->where('price', '<=', $request->max_price);
    }

    // ⭐ order by rating and latest
    $products = $query
        ->orderByDesc('rating')
        ->latest()
        ->paginate(12);

    return response()->json($products);
}
 


// test


// Get products in a specific category
public function productsByCategory(Request $request, $id)
{
    $perPage = $request->input('per_page', 12);

    $products = Product::with('images')
        ->where('status', 'published')
        ->where('category', $id)
        ->latest() // same as orderBy('created_at', 'desc')
        ->paginate($perPage);

    return response()->json($products);
}

/* =======================
       CATEGORY + PRODUCTS 🔥
    ======================= */
    public function productsByCategoryWithCategoryData(Request $request, $id)
    {
        $perPage = $request->input('per_page', 12);

        $category = Category::withCount('products')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $products = Product::with('images')
            ->where('status', 'published')
            ->where('category', $id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }



}
