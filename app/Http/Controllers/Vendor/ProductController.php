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
        $query = Product::where('business_id', auth()->user()->id)
            ->where('is_deleted', false)
            ->where('parent_product_id', null)
            ->with([
                'category',
                'brand',
                'variations' => function ($q) {
                    $q->where('is_deleted', false)->orderBy('is_default', 'desc')->orderBy('created_at', 'asc');
                }
            ]);

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

        foreach ($products->items() as $product) {
            if ((int) $product->product_type === 2 && $product->variations && $product->variations->isNotEmpty()) {
                $defaultVariation = $product->variations->firstWhere('is_default', 1)
                    ?: $product->variations->firstWhere('is_default', true)
                    ?: $product->variations->first();

                if ($defaultVariation) {
                    $product->default_variation = $defaultVariation;
                    $product->regular_price = $defaultVariation->regular_price;
                    $product->sale_price = $defaultVariation->sale_price;
                    $product->stock_quantity = $defaultVariation->stock_quantity ?? 0;
                    $product->product_status_stock = $defaultVariation->product_status_stock;
                    if (!empty($defaultVariation->product_sku)) {
                        $product->default_sku = $defaultVariation->product_sku;
                    }
                }
            }
        }

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
            'is_serialized' => 'nullable|boolean',
            
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
        'is_serialized' => (bool) ($request->is_serialized ?? false),
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
        if (!$query) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found!',
            ], 404);
        }

        // Determine effective product type (prefer explicit request value if sent)
        $productType = (int) ($request->product_type ?: ($query->product_type ?: 1));

        // Ensure product_type matches in database
        if ($query->product_type !== $productType) {
            $query->update(['product_type' => $productType]);
            $query->product_type = $productType;
        }

        // simple product create
        if($productType === 1){
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
        if($productType === 2){
            $isPublished = $request->has('is_published') 
                ? (filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN) || (int) $request->is_published === 1)
                : false;

            $query->update([
                'product_type' => 2,
                'is_published' => $isPublished,
            ]);

            // Sync publication status to all child variations
            Product::where('parent_product_id', $query->id)
                ->where('is_deleted', false)
                ->update(['is_published' => $isPublished]);

            $data = $query->fresh();
            return response()->json([
                'status' => true,
                'message' => 'Variable Product Step 2 saved successfully!',
                'data' => $data
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid product type.',
        ], 422);
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
            'is_serialized' => 'nullable|boolean',
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
            ProductAttributeTerm::firstOrCreate([
                'product_id' => $request->product_id,
                'attribute_id' => $request->attribute_id,
                'term_id' => $term_id,
            ], [
                'business_id' => auth()->user()->id,
                'sort_order' => 0,
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => 'Attribute and Terms saved successfully!',
        ], 201);
    }

    public function getSelectedAttributeTerms($product_id) {
        $data = ProductAttributeTerm::with(['attribute', 'term', 'mediaTerms'])
            ->where('product_id', $product_id)
            ->where(function ($q) {
                $q->where('business_id', auth()->user()->id)
                  ->orWhereNull('business_id');
            })
            ->orderBy('sort_order', 'asc')
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
                        'name' => $item->term->title ?? 'N/A',
                        'media' => $item->mediaTerms
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $formattedData
        ], 200);
    }

    public function clearTermImage(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'term_id'    => 'required',
        ]);

        $termImage = ProductAttributeTerm::where('product_id', $request->product_id)
            ->where('term_id', $request->term_id)
            ->where('business_id', auth()->user()->id)
            ->first();

        if ($termImage) {
            // Image ID ko null kar do
            $termImage->update([
                'image_id' => null
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Image cleared successfully!'
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Record not found'
        ], 404);
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



    public function syncTermImageToVariations(Request $request)
{
    $request->validate([
        'product_id' => 'required',
        'term_id'    => 'required', 
    ]);

    $attributeTerm = ProductAttributeTerm::where('product_id', $request->product_id)
        ->where('term_id', $request->term_id)
        ->where('business_id', auth()->user()->id)
        ->first();

    if (!$attributeTerm || !$attributeTerm->image_id) {
        return response()->json([
            'status' => false,
            'message' => 'Is term ke liye image assigned nahi hy.'
        ], 404);
    }

    $sourceImageId = $attributeTerm->image_id;

    $childProductIds = DB::table('product_variation_term_mappings')
        ->where('term_id', $request->term_id)
        ->pluck('product_id');

    $updatedRows = Product::whereIn('id', $childProductIds)
        ->where('parent_product_id', $request->product_id)
        ->where('business_id', auth()->user()->id)
        ->update(['image_id' => $sourceImageId]);

    return response()->json([
        'status' => true,
        'message' => "Image synced to $updatedRows variations successfully!",
        'image_id' => $sourceImageId
    ], 200);
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
                $prefix = $parentProduct->product_sku ?: (strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $parentProduct->title), 0, 4)) ?: 'PROD');
                $generatedSku = $prefix . '-' . ($index + 1);
                $slugBase = Str::slug($variationTitle) ?: 'variation';
                $uniqueSlug = $slugBase . '-' . substr($variationId, 0, 8);
                $isDefault = ($index === 0) ? 1 : 0;
                Product::create([
                    'id' => $variationId,
                    'parent_product_id' => $parentId,
                    'business_id' => $businessId,
                    'product_type' => 2,
                    'product_sku' => $generatedSku,
                    'title' => $variationTitle,
                    'product_slug' => $uniqueSlug,
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

        $variations = Product::where('parent_product_id', $id)
            ->where('is_deleted', 0)->with('variationImage')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage); 

        // Ensure at least one variation is marked default if collection is not empty
        $collection = $variations->getCollection();
        $hasDefault = $collection->contains(function ($item) {
            return (int) $item->is_default === 1 || $item->is_default === true;
        });

        if (!$hasDefault && $collection->isNotEmpty()) {
            $first = $collection->first();
            $first->is_default = 1;
            DB::table('products')->where('id', $first->id)->update(['is_default' => 1]);
        }

        $from = ($variations->currentPage() - 1) * $variations->perPage() + 1;
        $to = min($variations->currentPage() * $variations->perPage(), $variations->total());

        $variations->getCollection()->transform(function ($variation) {
            $variation->is_default = ((int) $variation->is_default === 1 || $variation->is_default === true) ? 1 : 0;
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
        $isPublished = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN) || (int) $request->is_published === 1;

        Product::where('business_id', auth()->user()->id)
            ->where('id', $request->id)
            ->update(['is_published' => $isPublished]);

        // When a parent product status is updated (Draft/Published), sync all child variations
        Product::where('parent_product_id', $request->id)
            ->where('is_deleted', false)
            ->update(['is_published' => $isPublished]);

        return response()->json([
            'status' => true,
            'message' => $isPublished ? 'Product and variations published!' : 'Product and variations set to draft!'
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
            'description',
            'image_id'
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

    public function saveTermImageIdInProductTerms(Request $request)
{
    // 1. Validation: Teeno IDs required hain aur UUID format mein honi chahiye
    $request->validate([
        'product_id' => 'required|uuid',
        'term_id'    => 'required|uuid',
        'image_id'   => 'nullable|uuid|exists:medias,id', // Image remove karne ke liye nullable rakha hai
    ]);

    try {
        $record = ProductAttributeTerm::where('product_id', $request->product_id)
            ->where('term_id', $request->term_id)
            ->first();

        if (!$record) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found for this product and term combination.'
            ], 404);
        }

        ProductAttributeTerm::where('product_id', $request->product_id)
            ->where('term_id', $request->term_id)
            ->update([
                'image_id' => $request->image_id,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Term image updated successfully.',
            'data' => [
                'image_id' => $request->image_id
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Collect a category ID and all of its active descendant category IDs for the vendor.
     */
    protected function getDescendantCategoryIds(string $businessId, string $rootCategoryId): array
    {
        $allCategories = Category::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->get(['id', 'parent_category_id']);

        $childrenByParent = [];
        foreach ($allCategories as $cat) {
            $parentId = (string) ($cat->parent_category_id ?? '');
            $childrenByParent[$parentId][] = (string) $cat->id;
        }

        $resultIds = [$rootCategoryId];
        $queue = [$rootCategoryId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            if (!empty($childrenByParent[$currentId])) {
                foreach ($childrenByParent[$currentId] as $childId) {
                    if (!in_array($childId, $resultIds, true)) {
                        $resultIds[] = $childId;
                        $queue[] = $childId;
                    }
                }
            }
        }

        return $resultIds;
    }

    /**
     * Format condition code into human-readable label for POS cards.
     */
    protected function formatConditionLabel($condition): string
    {
        $map = [
            1 => 'Brand New',
            2 => 'Refurbished',
            3 => 'Like New',
            4 => 'Excellent',
            5 => 'Good',
            6 => 'Fair',
            7 => 'For Parts / Not Working',
        ];

        if (is_numeric($condition) && isset($map[(int) $condition])) {
            return $map[(int) $condition];
        }

        return is_string($condition) && trim($condition) !== ''
            ? $condition
            : 'Brand New';
    }

    /**
     * Resolve full storage URL for a media path.
     */
    protected function resolveMediaUrl(?string $mediaPath): ?string
    {
        if (!$mediaPath) {
            return null;
        }
        if (str_starts_with($mediaPath, 'http://') || str_starts_with($mediaPath, 'https://')) {
            return $mediaPath;
        }
        return asset('storage/' . ltrim($mediaPath, '/'));
    }

    /**
     * Get published Simple and Variable products for the POS Terminal.
     */
    public function posProducts(Request $request)
    {
        $businessId = auth()->id();
        $hotList = $request->query('hot_list');
        $categoryParam = $request->query('category');
        $categoryId = $request->query('category_id');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('is_published', true)
            ->whereNull('parent_product_id')
            ->with([
                'category',
                'brand',
                'media',
                'variationImage',
                'variations' => function ($q) {
                    $q->where('is_deleted', false)
                        ->orderBy('is_default', 'desc')
                        ->orderBy('created_at', 'asc')
                        ->with(['variationImage', 'category', 'brand']);
                },
            ]);

        // Filter by category (and its descendant subcategories) when a specific category is active
        $isAllCategory = ($categoryId === '1') || (strtolower((string) $categoryParam) === 'all');
        if ($categoryId && !$isAllCategory && $hotList !== 'true') {
            $categoryIds = $this->getDescendantCategoryIds((string) $businessId, (string) $categoryId);
            $baseQuery->whereIn('category_id', $categoryIds);
        }

        // Filter by Hot List when hot_list=true (fallback to all published vendor products if none marked hot yet)
        if ($hotList === 'true' || $hotList === '1' || $hotList === true) {
            $hotClone = clone $baseQuery;
            if ($hotClone->where('is_hot', true)->exists()) {
                $baseQuery->where('is_hot', true);
            }
        }

        // Optional search by title or SKU across simple products and variations
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('product_sku', 'like', "%{$search}%")
                    ->orWhereHas('variations', function ($vq) use ($search) {
                        $vq->where('is_deleted', false)
                            ->where(function ($inner) use ($search) {
                                $inner->where('title', 'like', "%{$search}%")
                                    ->orWhere('product_sku', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $rootProducts = $baseQuery
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Collect all variation IDs and variable parent IDs to batch-load variation terms, attributes & term images
        $variationIds = [];
        $variableParentIds = [];
        foreach ($rootProducts as $rootProduct) {
            if ((int) $rootProduct->product_type === 2) {
                $variableParentIds[] = $rootProduct->id;
                foreach ($rootProduct->variations as $var) {
                    $variationIds[] = $var->id;
                }
            }
        }

        $termsByVariationId = [];
        if (!empty($variationIds)) {
            $termRows = DB::table('product_variation_term_mappings')
                ->join('terms', 'product_variation_term_mappings.term_id', '=', 'terms.id')
                ->join('attributes', 'terms.attribute_id', '=', 'attributes.id')
                ->whereIn('product_variation_term_mappings.product_id', $variationIds)
                ->select(
                    'product_variation_term_mappings.product_id',
                    'terms.id as term_id',
                    'terms.title as term_title',
                    'attributes.id as attribute_id',
                    'attributes.title as attribute_title'
                )
                ->get();

            foreach ($termRows as $row) {
                $termsByVariationId[$row->product_id][] = [
                    'term_id' => (string) $row->term_id,
                    'term_title' => $row->term_title,
                    'attribute_id' => (string) $row->attribute_id,
                    'attribute_title' => $row->attribute_title,
                ];
            }
        }

        // Batch-load product attributes and terms (plus term images) ordered by sort_order
        $attributesByParentId = [];
        $termMediaByParentAndTerm = [];
        if (!empty($variableParentIds)) {
            $attrTermRows = DB::table('product_attributes_terms')
                ->join('attributes', 'product_attributes_terms.attribute_id', '=', 'attributes.id')
                ->join('terms', 'product_attributes_terms.term_id', '=', 'terms.id')
                ->leftJoin('medias', 'product_attributes_terms.image_id', '=', 'medias.id')
                ->whereIn('product_attributes_terms.product_id', $variableParentIds)
                ->orderBy('product_attributes_terms.sort_order', 'asc')
                ->select(
                    'product_attributes_terms.product_id',
                    'product_attributes_terms.attribute_id',
                    'product_attributes_terms.term_id',
                    'product_attributes_terms.sort_order',
                    'attributes.title as attribute_title',
                    'terms.title as term_title',
                    'medias.media_path'
                )
                ->get();

            foreach ($attrTermRows as $row) {
                $pId = (string) $row->product_id;
                $aId = (string) $row->attribute_id;
                $tId = (string) $row->term_id;

                if ($row->media_path) {
                    $termMediaByParentAndTerm[$pId . ':' . $tId] = $row->media_path;
                }

                if (!isset($attributesByParentId[$pId][$aId])) {
                    $attributesByParentId[$pId][$aId] = [
                        'attribute_id' => $aId,
                        'attribute_name' => $row->attribute_title ?? 'Option',
                        'terms' => [],
                    ];
                }

                $alreadyAdded = false;
                foreach ($attributesByParentId[$pId][$aId]['terms'] as $existingTerm) {
                    if ($existingTerm['id'] === $tId) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                if (!$alreadyAdded) {
                    $attributesByParentId[$pId][$aId]['terms'][] = [
                        'id' => $tId,
                        'name' => $row->term_title,
                        'image' => $this->resolveMediaUrl($row->media_path),
                    ];
                }
            }
        }

        $posItems = [];

        foreach ($rootProducts as $product) {
            $parentMediaPath = optional($product->variationImage)->media_path
                ?: optional($product->media->first())->media_path;
            $parentImageUrl = $this->resolveMediaUrl($parentMediaPath);
            $parentBrandTitle = optional($product->brand)->title ?? 'N/A';
            $parentCategoryTitle = optional($product->category)->title ?? 'N/A';
            $parentConditionLabel = $this->formatConditionLabel($product->condition);

            // Variable product -> emit a single product card with its attributes, term pills, and variations list
            if ((int) $product->product_type === 2 && $product->variations->isNotEmpty()) {
                $formattedVariations = [];
                $fallbackAttributesMap = $attributesByParentId[(string) $product->id] ?? [];

                foreach ($product->variations as $variation) {
                    $mappedTerms = $termsByVariationId[$variation->id] ?? [];
                    $termIds = [];
                    $termMap = [];
                    $variantTitles = [];

                    foreach ($mappedTerms as $t) {
                        $tId = (string) ($t['term_id'] ?? '');
                        $aId = (string) ($t['attribute_id'] ?? '');
                        $tTitle = $t['term_title'] ?? null;
                        $aTitle = $t['attribute_title'] ?? 'Option';

                        if ($tId !== '') {
                            $termIds[] = $tId;
                        }
                        if ($aId !== '' && $tId !== '') {
                            $termMap[$aId] = $tId;
                            if (!isset($fallbackAttributesMap[$aId])) {
                                $fallbackAttributesMap[$aId] = [
                                    'attribute_id' => $aId,
                                    'attribute_name' => $aTitle,
                                    'terms' => [],
                                ];
                            }
                            $existsInAttr = false;
                            foreach ($fallbackAttributesMap[$aId]['terms'] as $et) {
                                if ($et['id'] === $tId) {
                                    $existsInAttr = true;
                                    break;
                                }
                            }
                            if (!$existsInAttr) {
                                $fallbackAttributesMap[$aId]['terms'][] = [
                                    'id' => $tId,
                                    'name' => $tTitle,
                                    'image' => null,
                                ];
                            }
                        }
                        if ($tTitle) {
                            $variantTitles[] = $tTitle;
                        }
                    }

                    // Resolve variation image: 1) variation.variationImage, 2) mapped term image, 3) parent gallery image
                    $varMediaPath = optional($variation->variationImage)->media_path;
                    if (!$varMediaPath && !empty($mappedTerms)) {
                        foreach ($mappedTerms as $t) {
                            $key = $product->id . ':' . ($t['term_id'] ?? '');
                            if (!empty($termMediaByParentAndTerm[$key])) {
                                $varMediaPath = $termMediaByParentAndTerm[$key];
                                break;
                            }
                        }
                    }
                    $variationImageUrl = $varMediaPath
                        ? $this->resolveMediaUrl($varMediaPath)
                        : $parentImageUrl;

                    $regularPrice = (float) ($variation->regular_price ?? $product->regular_price ?? 0);
                    $salePrice = (float) ($variation->sale_price ?? $product->sale_price ?? 0);
                    $effectivePrice = $salePrice > 0 ? $salePrice : $regularPrice;
                    $oldPrice = ($regularPrice > 0 && $salePrice > 0 && $regularPrice > $salePrice)
                        ? $regularPrice
                        : null;

                    $inStock = (bool) ($variation->product_status_stock ?? true);
                    $stockCount = $inStock
                        ? max(1, (int) ($variation->min_stock_alert ?? 1))
                        : 0;

                    $formattedVariations[] = [
                        'id' => $variation->id,
                        'parent_product_id' => $product->id,
                        'name' => $variation->title ?: $product->title,
                        'title' => $variation->title ?: $product->title,
                        'condition' => $variation->condition
                            ? $this->formatConditionLabel($variation->condition)
                            : $parentConditionLabel,
                        'brand' => optional($variation->brand)->title ?? $parentBrandTitle,
                        'category' => optional($variation->category)->title ?? $parentCategoryTitle,
                        'price' => $effectivePrice,
                        'oldPrice' => $oldPrice,
                        'regular_price' => $regularPrice,
                        'sale_price' => $salePrice,
                        'stock' => $stockCount,
                        'product_status_stock' => $inStock ? 1 : 0,
                        'sku' => (string) ($variation->product_sku ?: $product->product_sku ?: 'N/A'),
                        'product_sku' => (string) ($variation->product_sku ?: $product->product_sku ?: 'N/A'),
                        'is_default' => (int) ($variation->is_default ?? 0) === 1,
                        'image' => $variationImageUrl,
                        'term_ids' => $termIds,
                        'term_map' => $termMap,
                        'variants' => $variantTitles,
                        'vatStatus' => 'VAT Exempt',
                    ];
                }

                // Default variation is first because relation is ordered by is_default desc, created_at asc
                $defaultVariation = collect($formattedVariations)->firstWhere('is_default', true)
                    ?? $formattedVariations[0];

                $posItems[] = [
                    'id' => $product->id,
                    'variation_id' => $defaultVariation['id'],
                    'parent_product_id' => null,
                    'product_type' => 2,
                    'listing_type' => 'Variable',
                    'name' => $defaultVariation['name'] ?: $product->title,
                    'title' => $defaultVariation['title'] ?: $product->title,
                    'parent_title' => $product->title,
                    'condition' => $defaultVariation['condition'] ?: $parentConditionLabel,
                    'brand' => $defaultVariation['brand'] ?: $parentBrandTitle,
                    'category' => $defaultVariation['category'] ?: $parentCategoryTitle,
                    'category_id' => $product->category_id,
                    'brand_id' => $product->brand_id,
                    'price' => $defaultVariation['price'],
                    'oldPrice' => $defaultVariation['oldPrice'],
                    'regular_price' => $defaultVariation['regular_price'],
                    'sale_price' => $defaultVariation['sale_price'],
                    'stock' => $defaultVariation['stock'],
                    'product_status_stock' => $defaultVariation['product_status_stock'],
                    'sku' => $defaultVariation['sku'],
                    'product_sku' => $defaultVariation['product_sku'],
                    'variants' => $defaultVariation['variants'],
                    'image' => $defaultVariation['image'] ?: $parentImageUrl,
                    'vatStatus' => 'VAT Exempt',
                    'is_hot' => (bool) $product->is_hot,
                    'attributes' => array_values($fallbackAttributesMap),
                    'variations' => $formattedVariations,
                    'default_term_map' => $defaultVariation['term_map'] ?? [],
                ];
            } else {
                // Simple product (or variable product before variations are generated)
                $regularPrice = (float) ($product->regular_price ?? 0);
                $salePrice = (float) ($product->sale_price ?? 0);
                $effectivePrice = $salePrice > 0 ? $salePrice : $regularPrice;
                $oldPrice = ($regularPrice > 0 && $salePrice > 0 && $regularPrice > $salePrice)
                    ? $regularPrice
                    : null;

                $inStock = (bool) ($product->product_status_stock ?? true);
                $stockCount = $inStock
                    ? max(1, (int) ($product->min_stock_alert ?? 1))
                    : 0;

                $posItems[] = [
                    'id' => $product->id,
                    'variation_id' => null,
                    'parent_product_id' => null,
                    'product_type' => (int) ($product->product_type ?? 1),
                    'listing_type' => ((int) ($product->product_type ?? 1) === 2) ? 'Variable' : 'Simple',
                    'name' => $product->title,
                    'title' => $product->title,
                    'parent_title' => $product->title,
                    'condition' => $parentConditionLabel,
                    'brand' => $parentBrandTitle,
                    'category' => $parentCategoryTitle,
                    'category_id' => $product->category_id,
                    'brand_id' => $product->brand_id,
                    'price' => $effectivePrice,
                    'oldPrice' => $oldPrice,
                    'regular_price' => $regularPrice,
                    'sale_price' => $salePrice,
                    'stock' => $stockCount,
                    'product_status_stock' => $inStock ? 1 : 0,
                    'sku' => (string) ($product->product_sku ?: 'N/A'),
                    'product_sku' => (string) ($product->product_sku ?: 'N/A'),
                    'variants' => [],
                    'image' => $parentImageUrl,
                    'vatStatus' => 'VAT Exempt',
                    'is_hot' => (bool) $product->is_hot,
                    'attributes' => [],
                    'variations' => [],
                    'default_term_map' => [],
                ];
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'POS products retrieved successfully',
            'data' => $posItems,
        ], 200);
    }
}






