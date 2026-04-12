<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeTerm;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request){
        $searchByTitle = $request->search_by_title;
        $searchBySKU = $request->search_by_sku;
        $searchProductType = $request->search_product_type;
        $searchProductPurpose = $request->search_product_purpose;
        $searchProductStockStatus = $request->search_product_stock_status;
        $searchProductStatus = $request->search_product_status;
        $searchActiveFor = $request->search_active_for;
        $searchByBrand = $request->search_by_brand;
        $searchByCategory = $request->search_by_category;

        $perPage = $request->per_page ?? 10;
        $query = Product::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('parent_product_id', null);

        if($searchByTitle){
            $query->where('title', 'like', "%{$searchByTitle}%");
        }
        if($searchBySKU){
            $query->where('product_sku', 'like', "%{$searchBySKU}%");
        }

        if($searchProductType){
            $query->where('product_type', $searchProductType);
        }
        if($searchProductPurpose){
            $query->where('active_for', $searchProductPurpose);
        }
        if($searchProductStockStatus){
            $query->where('product_status_stock', $searchProductStockStatus);
        }
        if($searchProductStatus){
            $query->where('is_published', $searchProductStatus);
        }
        if($searchActiveFor){
            $query->where('is_pos', $searchActiveFor)->orWhere('is_web', $searchActiveFor);
        }
        if($searchByBrand){
            $query->where('brand_id', $searchActiveFor);
        }
        if($searchByBrand){
            $query->where('brand_id', $searchByBrand);
        }
        if($searchByCategory){
            $query->whereIn('category_id', $searchByBrand);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $from = ($products->currentPage() - 1) * $products->perPage() + 1;
        $to = min($products->currentPage() * $products->perPage(), $products->total());
        return response()->json([
            'status' => true,
            'message' => 'Category list retrieved successfully',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $products->total()
            ]
        ], 200);
    }

    public function createProductStep1(Request $request){
        $validateProduct = Validator::make($request->all(), [
            'active_for' => 'required|integer',
            'condition' => 'required|integer',
            'title' => 'required|string|min:3|max:500',
            'is_web' => 'required|boolean',
            'is_pos' => 'required|boolean',
            'is_hot' => 'required|boolean',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'sort_order' => 'required|integer',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'keywords' => 'nullable|string',
            'apply_stock_strictly' => 'required|boolean',
            'get_stock_alert' => 'required|boolean',
            
        ]);

        if($validateProduct->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Validaiton error!',
                'error' => $validateProduct->errors()->all()
            ], 422);
        }
        $data = Product::create([
        'active_for' => $request->active_for,
        'condition' => $request->condition,
        'title' => $request->title,
        'is_web' => $request->is_web,
        'is_pos' => $request->is_pos,
        'is_hot' => $request->is_hot,
        'category_id' => $request->category_id,
        'brand_id' => $request->brand_id,
        'sort_order' => $request->sort_order,
        'meta_title' => $request->meta_title,
        'meta_description' => $request->meta_description,
        'keywords' => $request->keywords,
        'days' => $request->days ?? 1,
        'minutes' => $request->minutes ?? 1,
        'hours' => $request->hours ?? 1,
        'apply_stock_strictly' => $request->apply_stock_strictly,
        'get_stock_alert' => $request->get_stock_alert,
        'description' => $request->description,
        'business_id' => auth()->user()->id
        ]);
        if ($request->has('product_image_ids')) {
        foreach ($request->product_image_ids as $image_id) {
            DB::table('product_images')->insert([
                'product_id' => $data->id,
                'media_id' => $image_id,
                'business_id' => auth()->id(),
            ]);
        }
    }
        return response()->json([
            'status' => true,
            'message' => 'Product first step created!',
            'data' => $data->load('media')
        ], 201);
    }

    public function updateProductType(Request $request){
        $validateRequest = Validator::make($request->all(), [
            'type' => 'required|integer',
            'product_id' => 'required|string|exists:products,id'
        ]);
        if($validateRequest->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'error' => $validateRequest->errors()->all()
            ], 422);
        }
        $data = Product::where('business_id', auth()->user()->id)->where('id', $request->product_id)->update(['product_type' => $request->type]);
        return response()->json([
            'status' => true,
            'message' => 'Record successfully updated!',
            'data' => $data
        ], 200);

    }

    public function createProductStep2(Request $request){
        
        $query = Product::where('business_id', auth()->user()->id)->where('id', $request->id)->first();
        // return response()->json([
        //     'data' => $query
        // ], 200);
        // simple product create
        if($query->product_type == 1){
            $validateSimpleProduct = Validator::make($request->all(), [
                'product_sku' => [
                'required',
                'string',
                Rule::unique('products', 'product_sku')->ignore($request->id),
                ],
                'regular_price' => 'required|numeric',
                'sale_price' => 'required|numeric',
                'is_on_sale' => 'required|boolean',
                'is_published' => 'required|boolean'
            ]);
            if($validateSimpleProduct->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error!',
                    'error' => $validateSimpleProduct->errors()->all()
                ], 422);
            }
            $query->update($request->all());
            $data = $query->fresh();
            return response()->json([
                'status' => true,
                'message' => 'Step 2 created successfully!',
                'data' => $data
            ], 200);
        }
        // variable product create
        if($query->product_type == 2){
            $query->update(['is_published' => $request->is_published]);
            $data = $query->fresh();
            return response()->json([
                'status' => true,
                'message' => 'Step 2 created successfully!',
                'data' => $data
            ], 200);
        }
    }

    public function editProductStep1(Request $request, $id){
        $product = Product::where('id', $id)->where('business_id', auth()->user()->id)->with('media')->first();

        if(!$product){
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
                
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'edit data retrieved!',
            'data' => $product
        ], 200);
    }


    public function updateProductStep1(Request $request)
    {
        $validateProduct = Validator::make($request->all(), [
            'active_for' => 'required|integer',
            'condition' => 'required|integer',
            'title' => 'required|string|min:3|max:500',
            'is_web' => 'required|boolean',
            'is_pos' => 'required|boolean',
            'is_hot' => 'required|boolean',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'sort_order' => 'required|integer',
            'apply_stock_strictly' => 'required|boolean',
            'get_stock_alert' => 'required|boolean',
            'product_image_ids' => 'nullable|array',
            'product_image_ids.*' => 'exists:medias,id',
        ]);

        if ($validateProduct->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error!',
                'error' => $validateProduct->errors()->all()
            ], 422);
        }

        $product = Product::where('business_id', auth()->id())
            ->where('id', $request->id)
            ->first();

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
            ], 404);
        }

        $product->update($request->except(['product_image_ids']));

        if ($request->filled('product_image_ids')) {
            $product->media()->sync(
                collect($request->product_image_ids)
                    ->mapWithKeys(fn($id) => [
                        $id => ['business_id' => auth()->id()]
                    ])
                    ->toArray()
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully!',
            'data' => $product->load('media')
        ], 200);
    }

    public function editProductSimpleStep2(Request $request, $id){
        $product = Product::where('id', $id)->where('business_id', auth()->user()->id)->first();

        if(!$product){
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
                
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'edit data retrieved!',
            'data' => $product
        ], 200);
    }

    // Load Attributes for selection
    public function loadAttributes(Request $request)
    {
        $product = Product::where('id', $request->id)->first();

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!'
            ], 404);
        }

        // already selected attributes
        $usedAttributeIds = ProductAttributeTerm::where('product_id', $product->id)
            ->pluck('attribute_id')
            ->toArray();

        $category = Category::where('id', $product->category_id)
            ->with(['attributes' => function ($query) use ($usedAttributeIds) {
                $query->whereNotIn('id', $usedAttributeIds);
            }])
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Attributes Fetched successfully!',
            'data' => $category->attributes
        ], 200);
    }

    // Load Terms for selection
    public function loadTerms(Request $request, $id){
        $terms = Term::where('attribute_id', $id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Terms Fetched successfully!',
            'data' => $terms
        ], 200);
    }

    // save selected attribute and terms
    public function saveSelectedAttributeTerms(Request $request){
        $validate = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'attribute_id' => 'required|string',
            'term_ids' => 'required|array'
        ]);
        if($validate->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validate->errors()->all()
            ], 422);
        }
        foreach($request->term_ids as $term_id){
            ProductAttributeTerm::create(['product_id' => $request->product_id, 'attribute_id' => $request->attribute_id, 'term_id' => $term_id, 'business_id' => auth()->user()->id]);
        }
        return response()->json([
            'status' => true,
            'message' => 'Attribute and Terms saved successfully!',
        ], 201);
        
    }
    
}
