<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeTerm;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        $query = Product::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('parent_product_id', null)->with(['category', 'brand']);

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
        if ($searchActiveFor) {
            if ($searchActiveFor == 1) {
                    $query->where('is_web', 1);
                } elseif ($searchActiveFor == 2) {
                    $query->where('is_pos', 1);
                }
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
        'business_id' => auth()->user()->id,
        'regular_price' => 0,
        'sale_price' => 0,
        'product_slug' => $request->title
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

    public function getProductType(Request $request, $id){
        $product = Product::where('business_id', auth()->user()->id)->where('id', $id)->select(['product_type', 'id'])->first();

        return response()->json([
            'status' => true,
            'message' => 'Product Type Fetched!',
            'data' => $product
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
                    'error' => $validateSimpleProduct->errors()
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
        $product = Product::where('id', $id)->where('business_id', auth()->user()->id)->with(['media', 'category'])->first();

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

        $data = $request->except(['product_image_ids']);
        $data['days'] = $request->filled('days') ? $request->days : 1;
        $data['hours'] = $request->filled('hours') ? $request->hours : 1;
        $data['minutes'] = $request->filled('minutes') ? $request->minutes : 1;
        $data['product_slug'] = $request->title;

        $product->update($data);

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
    public function loadAttributes(Request $request, $id)
{
    $product = Product::select('id', 'category_id')->find($id);

    if (!$product) {
        return response()->json(['status' => false, 'message' => 'Product not found!'], 404);
    }

    $usedAttributeIds = ProductAttributeTerm::where('product_id', $product->id)
        ->pluck('attribute_id')
        ->toArray();

    $attributes = Attribute::whereHas('categories', function ($query) use ($product) {
        $query->where('categories.id', $product->category_id);
    })
    ->whereNotIn('id', $usedAttributeIds)
    ->get(['id', 'title']);

    return response()->json([
        'status' => true,
        'message' => 'Attributes fetched successfully!',
        'data' => $attributes
    ], 200);
}

    // Load Terms for selection
    public function loadTerms(Request $request, $id){
        $terms = Term::where('attribute_id', $id)->where('is_deleted', false)->get();
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



    public function getSelectedAttributeTerms($product_id) {
    $data = ProductAttributeTerm::with(['attribute', 'term'])
        ->where('product_id', $product_id)->orderBy('sort_order', 'asc')
        ->where('business_id', auth()->user()->id)
        ->get();

        $formattedData = $data->groupBy('attribute_id')->map(function ($items) {
            $firstItem = $items->first();
            return [
                'attribute_id' => $firstItem->attribute_id,
                'attribute_name' => $firstItem->attribute->title ?? 'N/A',
                'sort_order' => $firstItem->sort_order ?? 0,
                'terms' => $items->map(function ($item) {
                    return [
                        'id' => $item->term_id,
                        'name' => $item->term->title ?? 'N/A'
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $formattedData
        ], 200);
    }


    public function updateAttributeSortOrder(Request $request)
{
    // Validation
    $validate = Validator::make($request->all(), [
        'product_id'   => 'required|string',
        'attribute_id' => 'required|string',
        'sort_order'   => 'required|integer'
    ]);

    if ($validate->fails()) {
        return response()->json([
            'status'  => false,
            'message' => 'Validation error!',
            'errors'  => $validate->errors()->all()
        ], 422);
    }

    try {
        $updated = ProductAttributeTerm::where('product_id', $request->product_id)
            ->where('attribute_id', $request->attribute_id)
            ->where('business_id', auth()->id()) // Security check
            ->update([
                'sort_order' => $request->sort_order,
                'updated_at' => now()
            ]);

        if ($updated) {
            return response()->json([
                'status'  => true,
                'message' => 'Sort order updated successfully.',
                'updated_count' => $updated
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'No records found to update.',
        ], 404);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong!',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    public function generateVariations(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $parentId = $request->product_id;
        $businessId = auth()->id();

        $parentProduct = DB::table('products')->where('id', $parentId)->first();

        if (!$parentProduct) {
            return response()->json(['status' => false, 'message' => 'Parent product not found'], 404);
        }

        $attributeTerms = DB::table('product_attributes_terms')
            ->where('product_id', $parentId)
            ->get()
            ->groupBy('attribute_id');

        if ($attributeTerms->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No attributes found'], 422);
        }

        $termGroups = [];
        foreach ($attributeTerms as $attrId => $terms) {
            $termGroups[] = $terms->pluck('term_id')->toArray();
        }

        $combinations = [[]];
        foreach ($termGroups as $group) {
            $newCombinations = [];
            foreach ($combinations as $combination) {
                foreach ($group as $term) {
                    $newCombinations[] = array_merge($combination, [$term]);
                }
            }
            $combinations = $newCombinations;
        }

        return DB::transaction(function () use ($combinations, $parentProduct, $parentId, $businessId) {
            $generatedCount = 0;

            foreach ($combinations as $index => $combo) {
                
                $termTitles = DB::table('terms')
                    ->whereIn('id', $combo)
                    ->pluck('title')
                    ->toArray();
                
                $variationTitle = $parentProduct->title . ' ' . implode(' ', $termTitles);
                // -----------------------------------------------

                $variationId = (string) Str::uuid();
                $newSku = $parentProduct->product_sku . '-' . ($index + 1);
                $isDefault = ($index === 0) ? 1 : 0;
                Product::create([
                    'id' => $variationId,
                    'parent_product_id' => $parentId,
                    'business_id' => $businessId,
                    'product_type' => 2,
                    // 'product_sku' => $newSku,
                    'title' => $variationTitle,
                    'product_slug' => $variationTitle,
                    'is_default' => $isDefault,
                    'condition' => $parentProduct->condition,
                    'is_web' => $parentProduct->is_web,
                    'is_pos' => $parentProduct->is_pos,
                    'is_hot' => $parentProduct->is_hot,
                    'category_id' => $parentProduct->category_id,
                    'brand_id' => $parentProduct->brand_id,
                    'regular_price' => 0,
                    'sale_price' => 0,
                    'product_status_stock' => 1,
                    'is_published' => 0, 
                    'meta_title' => $parentProduct->meta_title,
                    'meta_description' => $parentProduct->meta_description,
                    'keywords' => $parentProduct->keywords,
                    'description' => $parentProduct->description,
                ]);

                foreach ($combo as $termId) {
                    DB::table('product_variation_term_mappings')->insert([
                        'id' => (string) Str::uuid(),
                        'product_id' => $variationId,
                        'term_id' => $termId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $generatedCount++;
            }

            return response()->json([
                'status' => true, 
                'message' => $generatedCount . ' variations generated with titles!'
            ]);
        });
    }



    public function getProductVariations(Request $request, $id)
    {
        $perPage = $request->per_page ?? 4;
        
        $parentProduct = DB::table('products')->where('id', $id)->first();

        if (!$parentProduct) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $variations = DB::table('products')
            ->where('parent_product_id', $id)
            ->where('is_deleted', 0)
            ->paginate($perPage); 

        $from = ($variations->currentPage() - 1) * $variations->perPage() + 1;
        $to = min($variations->currentPage() * $variations->perPage(), $variations->total());

        $variations->getCollection()->transform(function ($variation) {
            $terms = DB::table('product_variation_term_mappings')
                ->join('terms', 'product_variation_term_mappings.term_id', '=', 'terms.id')
                ->join('attributes', 'terms.attribute_id', '=', 'attributes.id')
                ->where('product_variation_term_mappings.product_id', $variation->id)
                ->select(
                    'terms.id as term_id',
                    'terms.title as term_title',
                    'attributes.id as attribute_id',
                    'attributes.title as attribute_title'
                )
                ->get();

            $variation->variation_terms = $terms;
            return $variation;
        });

        return response()->json([
            'status' => true,
            'data' => $variations->items(),
            'pagination' => [
                'current_page' => $variations->currentPage(),
                'last_page' => $variations->lastPage(),
                'per_page' => $variations->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $variations->total()
            ]
        ]);
    }




    public function deleteProductAttribute(Request $request)
    {
        // Validation
        $validate = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'attribute_id' => 'required|string',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error!',
                'errors' => $validate->errors()->all()
            ], 422);
        }

        try {
            $deleted = ProductAttributeTerm::where('product_id', $request->product_id)
                ->where('attribute_id', $request->attribute_id)
                ->where('business_id', auth()->id()) 
                ->delete();

            if ($deleted) {
                return response()->json([
                    'status' => true,
                    'message' => 'Attribute and its terms deleted successfully from this product.',
                ], 200);
            }

            return response()->json([
                'status' => false,
                'message' => 'No record found to delete.',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function removeTermAndVariations(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'term_id'    => 'required|exists:terms,id',
        ]);

        $productId = $request->product_id;
        $termId = $request->term_id;

        return DB::transaction(function () use ($productId, $termId) {
            
            $variationIds = DB::table('product_variation_term_mappings')
                ->where('term_id', $termId)
                ->whereIn('product_id', function($query) use ($productId) {
                    $query->select('id')->from('products')->where('parent_product_id', $productId);
                })
                ->pluck('product_id');

            if ($variationIds->isNotEmpty()) {
                DB::table('products')->whereIn('id', $variationIds)->delete();
            }

            DB::table('product_attributes_terms')
                ->where('product_id', $productId)
                ->where('term_id', $termId)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Term and its associated variations removed successfully.',
                'deleted_variations_count' => count($variationIds)
            ]);
        });
    }


    public function deleteProductTerm(Request $request)
{
    // Validation
    $validate = Validator::make($request->all(), [
        'product_id' => 'required|string',
        'term_id'    => 'required|string',
    ]);

    if ($validate->fails()) {
        return response()->json([
            'status'  => false,
            'message' => 'Validation error!',
            'errors'  => $validate->errors()->all()
        ], 422);
    }

    try {
        $deleted = ProductAttributeTerm::where('product_id', $request->product_id)
            ->where('term_id', $request->term_id)
            ->where('business_id', auth()->id()) // Security check
            ->delete();

        if ($deleted) {
            return response()->json([
                'status'  => true,
                'message' => 'Term removed from product successfully.',
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Record not found or already deleted.',
        ], 404);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong!',
            'error'   => $e->getMessage()
        ], 500);
    }
}



    public function deleteProduct(Request $request, $id){
        $product = Product::where('id', $id)->first();

        if(!$product){
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }
        $product->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ], 200);
    }

    public function productPublishUpdate(Request $request){
        Product::where('business_id', auth()->user()->id)->where('id', $request->id)->update(['is_published' => $request->is_published]);
        return response()->json([
            'status' => true,
            'message' => 'Status updated!'
        ]);
    }


    public function updateVariation(Request $request, $id)
{
    // 1. Validation
    $request->validate([
        'is_default'    => 'sometimes|boolean',
        'title'         => 'sometimes|string|max:255',
        'product_sku'   => 'sometimes|string|unique:products,product_sku,' . $id,
        'regular_price' => 'sometimes|numeric|min:0',
        'sale_price'    => 'sometimes|numeric|min:0',
    ]);

    $variation = Product::where('id', $id)->where('product_type', 2)->first();

    if (!$variation) {
        return response()->json(['status' => false, 'message' => 'Variation not found'], 404);
    }

    return DB::transaction(function () use ($request, $variation, $id) {
        
        // --- DEFAULT LOGIC START ---
        if ($request->has('is_default')) {
            $newStatus = (int) $request->is_default;

            if ($newStatus == 1) {
                Product::where('parent_product_id', $variation->parent_product_id)
                    ->where('product_type', 2)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => 0]);
                
                $variation->is_default = 1;
            } 
            else {
                $variation->is_default = 0;
                $variation->save(); 

                $anyDefaultExists = Product::where('parent_product_id', $variation->parent_product_id)
                    ->where('product_type', 2)
                    ->where('is_default', 1)
                    ->exists();

                if (!$anyDefaultExists) {
                    $fallback = Product::where('parent_product_id', $variation->parent_product_id)
                        ->where('product_type', 2)
                        ->where('id', '!=', $id)
                        ->first();

                    if ($fallback) {
                        $fallback->update(['is_default' => 1]);
                    } else {
                        $variation->is_default = 1;
                    }
                }
            }
        }
        // --- DEFAULT LOGIC END ---

        $dataToUpdate = $request->only([
            'is_on_sale',
            'title', 
            'product_sku', 
            'regular_price', 
            'sale_price', 
            'product_status_stock',
            'meta_title',
            'meta_description',
            'keywords',
            'min_stock_alert',
            'release_year',
            'network',
            'SIM',
            'carrier_option',
            'description'
        ]);

        if ($request->has('title')) {
            $dataToUpdate['product_slug'] = $request->title;
        }

        $variation->update($dataToUpdate);

        return response()->json([
            'status' => true,
            'message' => 'Variation updated successfully with consistency check.',
            'data' => $variation->fresh()
        ]);
    });
}
    
}






