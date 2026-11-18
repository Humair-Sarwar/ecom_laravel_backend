<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeTerm;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierLedger;
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
            $updatePayload = $request->all();
            if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'specs')) {
                unset($updatePayload['specs']);
            }
            $query->update($updatePayload);
            $data = $query->fresh(['category', 'brand', 'media', 'variationImage']);
            $mediaPath = optional($data->media->first())->media_path ?: optional($data->variationImage)->media_path;
            $stockItems = [[
                'id' => $data->id,
                'type' => 'product',
                'name' => trim((string) $data->title) ?: ($data->product_sku ? 'Product ' . $data->product_sku : 'Product Item'),
                'sku' => $data->product_sku,
                'barcode' => $data->product_sku,
                'condition' => $data->condition,
                'category_name' => optional($data->category)->title ?: 'Uncategorized',
                'brand_name' => optional($data->brand)->title ?: 'No Brand',
                'is_serialized' => (bool) $data->is_serialized,
                'image' => $mediaPath ? asset('storage/' . ltrim($mediaPath, '/')) : null,
            ]];

            return response()->json([
                'status' => true,
                'message' => 'Step 2 created successfully!',
                'data' => $data,
                'stock_items' => $stockItems,
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

            // Sync publication & stock/serialization settings to all child variations
            Product::where('parent_product_id', $query->id)
                ->where('is_deleted', false)
                ->update([
                    'is_published' => $isPublished,
                    'is_serialized' => (bool) $query->is_serialized,
                    'apply_stock_strictly' => (bool) $query->apply_stock_strictly,
                    'get_stock_alert' => (bool) $query->get_stock_alert,
                ]);

            $data = $query->fresh(['category', 'brand', 'media']);
            $variations = Product::where('parent_product_id', $query->id)
                ->where('is_deleted', false)
                ->with(['variationImage'])
                ->get();

            $parentCat = optional($data->category)->title ?: 'Uncategorized';
            $parentBrand = optional($data->brand)->title ?: 'No Brand';
            $parentMedia = optional($data->media->first())->media_path;

            $stockItems = $variations->map(function ($v) use ($data, $parentCat, $parentBrand, $parentMedia) {
                $varMedia = optional($v->variationImage)->media_path ?: $parentMedia;
                $varTitle = trim((string) $v->title) ?: trim((string) $data->title) ?: ($v->product_sku ? 'Product ' . $v->product_sku : 'Variation Item');
                return [
                    'id' => $v->id,
                    'type' => 'product',
                    'name' => $varTitle,
                    'sku' => $v->product_sku,
                    'barcode' => $v->product_sku,
                    'condition' => $v->condition ?? $data->condition,
                    'category_name' => $parentCat,
                    'brand_name' => $parentBrand,
                    'is_serialized' => (bool) ($v->is_serialized || $data->is_serialized),
                    'image' => $varMedia ? asset('storage/' . ltrim($varMedia, '/')) : null,
                ];
            })->values()->all();

            return response()->json([
                'status' => true,
                'message' => 'Variable Product Step 2 saved successfully!',
                'data' => $data,
                'stock_items' => $stockItems,
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

        // Sync stock/serialization flags to existing child variations if any
        Product::where('parent_product_id', $product->id)
            ->where('is_deleted', false)
            ->update([
                'is_serialized' => (bool) $product->is_serialized,
                'apply_stock_strictly' => (bool) $product->apply_stock_strictly,
                'get_stock_alert' => (bool) $product->get_stock_alert,
            ]);

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
                    'apply_stock_strictly' => (bool) $parentProduct->apply_stock_strictly,
                    'get_stock_alert' => (bool) $parentProduct->get_stock_alert,
                    'is_serialized' => (bool) $parentProduct->is_serialized,
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

        $variations->getCollection()->transform(function ($variation) use ($parentProduct) {
            $variation->is_default = ((int) $variation->is_default === 1 || $variation->is_default === true) ? 1 : 0;
            $variation->is_serialized = (bool) ($variation->is_serialized || ($parentProduct->is_serialized ?? false));
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
        $brandId = $request->query('brand_id');
        $search = trim((string) $request->query('search', ''));
        $stockStatus = trim((string) $request->query('stock_status', 'All'));
        $publishStatus = trim((string) $request->query('status', 'Published'));

        $baseQuery = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('is_published', strtolower($publishStatus) !== 'draft')
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

        // Optional filter by brand_id
        if (!empty($brandId) && strtolower((string) $brandId) !== 'all') {
            $baseQuery->where('brand_id', $brandId);
        }

        // Optional search by title, SKU, or additional barcodes in specs
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('product_sku', 'ilike', "%{$search}%")
                    ->orWhereRaw("CAST(specs AS TEXT) ILIKE ?", ["%{$search}%"])
                    ->orWhereHas('variations', function ($vq) use ($search) {
                        $vq->where('is_deleted', false)
                            ->where(function ($inner) use ($search) {
                                $inner->where('title', 'ilike', "%{$search}%")
                                    ->orWhere('product_sku', 'ilike', "%{$search}%");
                            });
                    });
            });
        }

        // Optional filter by Stock Status (In Stock / Out of Stock)
        if (strtolower($stockStatus) === 'in stock') {
            $baseQuery->where(function ($q) {
                $q->where('stock_quantity', '>', 0)
                    ->orWhereHas('variations', function ($vq) {
                        $vq->where('is_deleted', false)->where('stock_quantity', '>', 0);
                    });
            });
        } elseif (strtolower($stockStatus) === 'out of stock') {
            $baseQuery->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('product_type', '!=', 2)->where('stock_quantity', '<=', 0);
                })->orWhere(function ($vq) {
                    $vq->where('product_type', 2)->whereDoesntHave('variations', function ($sub) {
                        $sub->where('is_deleted', false)->where('stock_quantity', '>', 0);
                    });
                });
            });
        } elseif (strtolower($stockStatus) === 'unmanaged') {
            $baseQuery->where('apply_stock_strictly', false);
        } elseif (strtolower($stockStatus) === 'low stock') {
            $baseQuery->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('product_type', '!=', 2)
                        ->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'min_stock_alert');
                })->orWhereHas('variations', function ($vq) {
                    $vq->where('is_deleted', false)
                        ->where('stock_quantity', '>', 0)
                        ->whereColumn('stock_quantity', '<=', 'min_stock_alert');
                });
            });
        }

        // Optional filter by option/attribute terms (series, quality, color, type, ram, storage, processor)
        $optionFilterKeys = ['series', 'quality', 'color', 'type', 'ram', 'storage', 'processor'];
        foreach ($optionFilterKeys as $optKey) {
            $optVal = trim((string) $request->query($optKey, ''));
            if ($optVal === '') {
                continue;
            }
            $lowerVal = strtolower($optVal);
            $matchingProductIds = DB::table('product_attributes_terms')
                ->join('terms', 'product_attributes_terms.term_id', '=', 'terms.id')
                ->whereRaw('LOWER(terms.title) = ?', [$lowerVal])
                ->pluck('product_attributes_terms.product_id')
                ->toArray();

            $baseQuery->where(function ($q) use ($matchingProductIds, $optVal) {
                if (!empty($matchingProductIds)) {
                    $q->whereIn('id', $matchingProductIds);
                } else {
                    $q->whereRaw('1 = 0');
                }
                $q->orWhere('title', 'ilike', "%{$optVal}%")
                    ->orWhere('description', 'ilike', "%{$optVal}%");
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
                    $isOnSale = (bool) ($variation->is_on_sale ?? $product->is_on_sale ?? false);
                    $effectivePrice = ($isOnSale && $salePrice > 0)
                        ? $salePrice
                        : ($regularPrice > 0 ? $regularPrice : $salePrice);
                    $oldPrice = ($isOnSale && $regularPrice > 0 && $salePrice > 0 && $regularPrice > $salePrice)
                        ? $regularPrice
                        : null;

                    $stockCount = (int) ($variation->stock_quantity ?? 0);
                    $inStock = (bool) ($variation->product_status_stock ?? ($stockCount > 0));

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
                        'is_on_sale' => $isOnSale,
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
                    'active_for' => (int) ($product->active_for ?? 1),
                    'condition_id' => (int) ($product->condition ?? 1),
                    'min_stock_alert' => (int) ($product->min_stock_alert ?? 1),
                    'is_serialized' => (bool) ($product->is_serialized ?? false),
                    'is_on_sale' => (bool) ($product->is_on_sale ?? false),
                    'apply_stock_strictly' => (bool) ($product->apply_stock_strictly ?? true),
                    'get_stock_alert' => (bool) ($product->get_stock_alert ?? true),
                    'description' => (string) ($product->description ?? ''),
                    'had_stock' => ((int) ($defaultVariation['stock'] ?? 0) > 0),
                    'is_hot' => (bool) $product->is_hot,
                    'release_year' => $product->release_year,
                    'specs' => is_array($product->specs ?? null) ? $product->specs : (is_string($product->specs ?? null) ? json_decode($product->specs, true) : []),
                    'barcodes' => $this->formatProductBarcodes($product),
                    'attributes' => array_values($fallbackAttributesMap),
                    'variations' => $formattedVariations,
                    'default_term_map' => $defaultVariation['term_map'] ?? [],
                ];
            } else {
                // Simple product (or variable product before variations are generated)
                $regularPrice = (float) ($product->regular_price ?? 0);
                $salePrice = (float) ($product->sale_price ?? 0);
                $isOnSale = (bool) ($product->is_on_sale ?? false);
                $effectivePrice = ($isOnSale && $salePrice > 0)
                    ? $salePrice
                    : ($regularPrice > 0 ? $regularPrice : $salePrice);
                $oldPrice = ($isOnSale && $regularPrice > 0 && $salePrice > 0 && $regularPrice > $salePrice)
                    ? $regularPrice
                    : null;

                $stockCount = (int) ($product->stock_quantity ?? 0);
                $inStock = (bool) ($product->product_status_stock ?? ($stockCount > 0));

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
                    'active_for' => (int) ($product->active_for ?? 1),
                    'condition_id' => (int) ($product->condition ?? 1),
                    'min_stock_alert' => (int) ($product->min_stock_alert ?? 1),
                    'is_serialized' => (bool) ($product->is_serialized ?? false),
                    'is_on_sale' => (bool) ($product->is_on_sale ?? false),
                    'apply_stock_strictly' => (bool) ($product->apply_stock_strictly ?? true),
                    'get_stock_alert' => (bool) ($product->get_stock_alert ?? true),
                    'description' => (string) ($product->description ?? ''),
                    'had_stock' => ($stockCount > 0),
                    'is_hot' => (bool) $product->is_hot,
                    'release_year' => $product->release_year,
                    'specs' => is_array($product->specs ?? null) ? $product->specs : (is_string($product->specs ?? null) ? json_decode($product->specs, true) : []),
                    'barcodes' => $this->formatProductBarcodes($product),
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

    /**
     * Quick add a POS product and optionally record a Purchase Order + Supplier Ledger entry.
     */
    public function quickAddPosProduct(Request $request)
    {
        $businessId = auth()->id();
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
            'condition' => 'required',
            'category_id' => 'required|uuid|exists:categories,id',
            'brand_id' => 'required|uuid|exists:brands,id',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'product_sku' => 'nullable|string|max:120',
            'purchase_order' => 'nullable|boolean',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $conditionMap = [
            'Brand New' => 1,
            'Refurbished' => 2,
            'Like New' => 3,
            'Used' => 5,
        ];
        $rawCondition = (string) $request->input('condition');
        $conditionVal = $conditionMap[$rawCondition] ?? (is_numeric($rawCondition) ? (int) $rawCondition : 1);

        $baseSlug = Str::slug((string) $request->input('title')) ?: 'product';
        $productSlug = $baseSlug;
        if (Product::where('product_slug', $productSlug)->exists()) {
            $productSlug = $baseSlug . '-' . Str::lower(Str::random(5));
        }

        $isPurchaseOrder = $request->boolean('purchase_order', false);
        $manageStock = $request->boolean('manage_stock', true);
        $inStock = ($request->input('status', 'In Stock') !== 'Out of Stock');
        $serialItems = $request->input('serial_items', []);
        $hasSerialItems = is_array($serialItems) && count($serialItems) > 0;

        if ($isPurchaseOrder && $hasSerialItems) {
            $seenImeis = [];
            foreach ($serialItems as $sItem) {
                $imei = trim((string) ($sItem['imei_serial_no'] ?? ''));
                if ($imei === '') {
                    continue;
                }
                $lower = strtolower($imei);
                if (in_array($lower, $seenImeis, true)) {
                    return response()->json([
                        'status' => false,
                        'message' => "Duplicate IMEI or Serial Number \"{$imei}\" in order.",
                    ], 422);
                }
                $seenImeis[] = $lower;

                $existsInPo = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($businessId) {
                    $q->where('business_id', $businessId);
                })
                    ->where(function ($q) use ($imei) {
                        $q->where('imei_serial_no', $imei)
                            ->orWhereRaw("? = ANY(string_to_array(replace(imei_serial_no, ' ', ''), ','))", [$imei]);
                    })
                    ->exists();

                $existsInProducts = Product::where('business_id', $businessId)
                    ->where('is_deleted', false)
                    ->where('product_sku', $imei)
                    ->exists();

                if ($existsInPo || $existsInProducts) {
                    return response()->json([
                        'status' => false,
                        'message' => "IMEI or Serial Number \"{$imei}\" already exists in the system.",
                    ], 422);
                }
            }
        }
        $poQty = $hasSerialItems ? count($serialItems) : max(0, (int) $request->input('po_quantity', 0));
        $packQty = max(1, (int) $request->input('pack_quantity', 1));

        $initialStock = 0;
        if ($isPurchaseOrder) {
            $initialStock = max(1, $poQty);
        }

        DB::beginTransaction();
        try {
            $productData = [
                'business_id' => $businessId,
                'active_for' => 1,
                'condition' => $conditionVal,
                'title' => trim((string) $request->input('title')),
                'product_slug' => $productSlug,
                'category_id' => $request->input('category_id'),
                'brand_id' => $request->input('brand_id'),
                'product_type' => 1,
                'is_web' => false,
                'is_pos' => true,
                'is_published' => true,
                'is_hot' => $request->boolean('is_hot', false),
                'is_on_sale' => $request->boolean('is_on_sale', false),
                'get_stock_alert' => $request->boolean('is_serialized', false)
                    ? true
                    : ($manageStock ? $request->boolean('get_stock_alert', true) : false),
                'apply_stock_strictly' => $request->boolean('is_serialized', false) ? true : $manageStock,
                'is_serialized' => $request->boolean('is_serialized', false),
                'product_status_stock' => $inStock,
                'min_stock_alert' => max(0, (int) $request->input('min_stock_alert', 1)),
                'regular_price' => (float) $request->input('regular_price', 0),
                'sale_price' => (float) $request->input('sale_price', 0),
                'stock_quantity' => $initialStock,
                'release_year' => is_numeric($request->input('release_year')) ? (int) $request->input('release_year') : null,
                'description' => $request->input('comments'),
            ];

            $sku = trim((string) $request->input('product_sku', ''));
            if ($sku !== '') {
                $productData['product_sku'] = $sku;
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'specs')) {
                $specsPayload = array_filter([
                    'warranty' => $request->input('warranty'),
                    'series' => $request->input('series'),
                    'color' => $request->input('color'),
                    'quality' => $request->input('quality'),
                    'type' => $request->input('type'),
                    'network' => $request->input('network'),
                    'storage' => $request->input('storage'),
                    'ram' => $request->input('ram'),
                    'processor' => $request->input('processor'),
                    'release_year' => $request->input('release_year'),
                    'carrier_option' => $request->input('carrier_option'),
                    'sim' => $request->input('sim'),
                    'battery_health' => $request->input('battery_health'),
                    'screen_size' => $request->input('screen_size'),
                ], fn($v) => $v !== null && $v !== '');
                if (!empty($specsPayload)) {
                    $productData['specs'] = $specsPayload;
                }
            }

            $product = Product::create($productData);

            // Optionally create Purchase Order & Supplier Ledger entries
            if ($isPurchaseOrder && $request->filled('supplier_id')) {
                $supplier = Supplier::where('business_id', $businessId)
                    ->where('is_deleted', false)
                    ->find($request->input('supplier_id'));

                if (!$supplier) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'Selected supplier not found.',
                    ], 404);
                }

                do {
                    $orderNo = (string) random_int(10000000, 99999999);
                } while (PurchaseOrder::where('business_id', $businessId)->where('order_no', $orderNo)->exists());

                $qty = max(1, $poQty);
                $unitCost = (float) $request->input('unit_cost', 0);
                $packPrice = (float) $request->input('pack_price', 0);
                $effectiveUnitCost = $unitCost > 0 ? $unitCost : $packPrice;

                if ($hasSerialItems) {
                    $grandTotal = 0;
                    foreach ($serialItems as $sItem) {
                        $sUnit = (float) ($sItem['unit_cost'] ?? 0);
                        $sPack = (float) ($sItem['pack_price'] ?? 0);
                        $grandTotal += ($sUnit > 0 ? $sUnit : $sPack);
                    }
                } else {
                    $grandTotal = $effectiveUnitCost * $qty;
                }

                $cashPaid = (float) $request->input('cash_paid', 0);
                $cardPaid = (float) $request->input('card_paid', 0);
                $bankPaid = (float) $request->input('bank_paid', 0);
                $paidAmount = $cashPaid + $cardPaid + $bankPaid;
                $balance = max(0.00, $grandTotal - $paidAmount);

                $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                    ?: ($user->name ?? $user->email ?? 'Vendor');

                $orderDate = $request->input('order_date') ?: now()->toDateString();

                $order = PurchaseOrder::create([
                    'business_id' => $businessId,
                    'supplier_id' => $supplier->id,
                    'order_no' => $orderNo,
                    'order_date' => $orderDate,
                    'status' => 'completed',
                    'fulfillment_status' => 'fulfilled',
                    'is_trade_in' => false,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'cash_paid' => $cashPaid,
                    'card_paid' => $cardPaid,
                    'bank_paid' => $bankPaid,
                    'use_today_sales' => $request->boolean('use_today_sales', false),
                    'notes' => $request->input('po_description'),
                    'created_by' => $businessId,
                    'created_by_name' => $creatorName,
                ]);

                if ($hasSerialItems) {
                    foreach ($serialItems as $sItem) {
                        $sUnit = (float) ($sItem['unit_cost'] ?? 0);
                        $sPack = (float) ($sItem['pack_price'] ?? 0);
                        $sEffective = $sUnit > 0 ? $sUnit : $sPack;
                        PurchaseOrderItem::create([
                            'purchase_order_id' => $order->id,
                            'product_id' => $product->id,
                            'product_name' => $product->title,
                            'sku' => $product->product_sku,
                            'condition' => $rawCondition,
                            'category_name' => optional($product->category)->title,
                            'brand_name' => optional($product->brand)->title,
                            'warranty' => $request->input('warranty'),
                            'year' => $request->input('release_year'),
                            'unit_cost' => $sEffective,
                            'pack_price' => $sPack > 0 ? $sPack : null,
                            'quantity' => 1,
                            'received_quantity' => 1,
                            'total_cost' => $sEffective,
                            'description' => $request->input('po_description'),
                            'imei_serial_no' => $sItem['imei_serial_no'] ?? null,
                            'received_at' => now(),
                            'item_status' => 'ready_for_sell',
                        ]);
                    }
                } else {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->title,
                        'sku' => $product->product_sku,
                        'condition' => $rawCondition,
                        'category_name' => optional($product->category)->title,
                        'brand_name' => optional($product->brand)->title,
                        'warranty' => $request->input('warranty'),
                        'year' => $request->input('release_year'),
                        'unit_cost' => $effectiveUnitCost,
                        'pack_price' => $packPrice > 0 ? $packPrice : null,
                        'quantity' => $qty,
                        'received_quantity' => $qty,
                        'total_cost' => $grandTotal,
                        'description' => $request->input('po_description'),
                        'imei_serial_no' => $request->input('imei_serial_no'),
                        'received_at' => now(),
                        'item_status' => 'ready_for_sell',
                    ]);
                }

                $runningBalance = (float) $supplier->balance + $grandTotal;
                SupplierLedger::create([
                    'business_id' => $businessId,
                    'supplier_id' => $supplier->id,
                    'reference_no' => $orderNo,
                    'transaction_type' => 'purchase_order',
                    'purchase_amount' => $grandTotal,
                    'payment_amount' => 0.00,
                    'balance' => $runningBalance,
                    'payment_type' => 'N/A',
                    'transaction_date' => $orderDate,
                    'description' => 'Quick Add Product Purchase Order',
                    'use_pos_balance' => false,
                    'created_by' => $businessId,
                    'created_by_name' => $creatorName,
                    'created_at' => now()->subSeconds(10),
                ]);

                $paymentSplits = [];
                if ($cashPaid > 0) $paymentSplits[] = ['type' => 'Cash', 'amount' => $cashPaid, 'seconds' => 3];
                if ($bankPaid > 0) $paymentSplits[] = ['type' => 'Bank', 'amount' => $bankPaid, 'seconds' => 2];
                if ($cardPaid > 0) $paymentSplits[] = ['type' => 'Card', 'amount' => $cardPaid, 'seconds' => 1];

                foreach ($paymentSplits as $split) {
                    $runningBalance = max(0.00, $runningBalance - $split['amount']);
                    SupplierLedger::create([
                        'business_id' => $businessId,
                        'supplier_id' => $supplier->id,
                        'reference_no' => $orderNo,
                        'transaction_type' => 'payment',
                        'purchase_amount' => 0.00,
                        'payment_amount' => $split['amount'],
                        'balance' => $runningBalance,
                        'payment_type' => $split['type'],
                        'transaction_date' => $orderDate,
                        'description' => 'Purchase Order Payment',
                        'use_pos_balance' => $request->boolean('use_today_sales', false),
                        'created_by' => $businessId,
                        'created_by_name' => $creatorName,
                        'created_at' => now()->subSeconds($split['seconds']),
                    ]);
                }

                $supplier->update(['balance' => $runningBalance]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Product added successfully!',
                'data' => $product->fresh(['category', 'brand']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to add product: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function formatProductBarcodes($product): array
    {
        $specs = is_array($product->specs ?? null)
            ? $product->specs
            : (is_string($product->specs ?? null) ? json_decode($product->specs, true) : []);
        $list = is_array($specs['barcodes'] ?? null) ? $specs['barcodes'] : [];

        if (empty($list) && !empty($product->product_sku)) {
            $list = [[
                'id' => 'primary',
                'barcode' => (string) $product->product_sku,
                'is_default' => true,
                'created_at' => $product->created_at
                    ? $product->created_at->toIso8601String()
                    : now()->toIso8601String(),
            ]];
        }

        return $list;
    }

    public function updatePosProduct(Request $request)
    {
        $businessId = auth()->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid',
            'title' => 'required|string|max:500',
            'condition' => 'required',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'brand_id' => 'nullable|uuid|exists:brands,id',
            'regular_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'product_sku' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($request->input('id'));

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $newSku = trim((string) $request->input('product_sku', ''));
        if ($newSku !== '' && $newSku !== (string) $product->product_sku) {
            $skuExists = Product::where('business_id', $businessId)
                ->where('is_deleted', false)
                ->where('id', '!=', $product->id)
                ->where('product_sku', $newSku)
                ->exists();
            if ($skuExists) {
                return response()->json([
                    'status' => false,
                    'message' => "SKU / Barcode \"{$newSku}\" is already assigned to another product.",
                ], 422);
            }
            $product->product_sku = $newSku;

            if (\Illuminate\Support\Facades\Schema::hasTable('product_barcodes')) {
                $defaultBarcode = \App\Models\ProductBarcode::where('product_id', $product->id)
                    ->where('is_default', true)
                    ->first();
                if ($defaultBarcode) {
                    $defaultBarcode->barcode = $newSku;
                    $defaultBarcode->save();
                } else {
                    \App\Models\ProductBarcode::create([
                        'product_id' => $product->id,
                        'business_id' => $businessId,
                        'barcode' => $newSku,
                        'is_default' => true,
                    ]);
                }
            }
        }

        $conditionMap = [
            'Brand New' => 1,
            'Refurbished' => 2,
            'Like New' => 3,
            'Excellent' => 4,
            'Good' => 5,
            'Used' => 5,
            'Fair' => 6,
            'For Parts' => 7,
        ];
        $rawCondition = (string) $request->input('condition');
        $conditionVal = $conditionMap[$rawCondition] ?? (is_numeric($rawCondition) ? (int) $rawCondition : (int) ($product->condition ?? 1));

        $product->active_for = (int) $request->input('active_for', $product->active_for ?? 1);
        $product->condition = $conditionVal;
        $product->title = trim((string) $request->input('title'));
        if ($request->filled('category_id')) {
            $product->category_id = $request->input('category_id');
        }
        if ($request->filled('brand_id')) {
            $product->brand_id = $request->input('brand_id');
        }

        $inStock = ($request->input('status', 'In Stock') !== 'Out of Stock');
        $product->product_status_stock = $inStock;
        $product->min_stock_alert = max(0, (int) $request->input('min_stock_alert', 0));
        $product->regular_price = (float) $request->input('regular_price', 0);
        $product->sale_price = (float) $request->input('sale_price', 0);

        if ((int) ($product->stock_quantity ?? 0) <= 0 && $request->has('is_serialized')) {
            $product->is_serialized = $request->boolean('is_serialized', false);
        }

        $releaseYearInput = $request->input('release_year');
        $product->release_year = is_numeric($releaseYearInput) ? (int) $releaseYearInput : null;
        $product->description = $request->input('comments');
        $product->is_on_sale = $request->boolean('is_on_sale', false);
        $product->apply_stock_strictly = $product->is_serialized ? true : $request->boolean('apply_stock_strictly', true);
        $product->get_stock_alert = $product->is_serialized
            ? true
            : ($product->apply_stock_strictly ? $request->boolean('get_stock_alert', true) : false);
        $product->is_hot = $request->boolean('is_hot', false);

        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'specs')) {
            $existingSpecs = is_array($product->specs ?? null)
                ? $product->specs
                : (is_string($product->specs ?? null) ? (json_decode($product->specs, true) ?: []) : []);

            $mergedSpecs = array_merge($existingSpecs, [
                'min_sale_price_limit' => $request->input('min_sale_price_limit', 0),
                'unit_of_measure' => $request->input('unit_of_measure', 'Piece'),
                'pack_quantity' => max(1, (int) $request->input('pack_quantity', 1)),
                'tax_definition' => $request->input('tax_definition', 'N/A'),
                'imei_serial_type' => $request->input('imei_serial_type', 'Imei No'),
                'imei_serial_no' => $request->input('imei_serial_no', ''),
                'warranty' => $request->input('warranty', ''),
                'series' => $request->input('series', ''),
                'color' => $request->input('color', ''),
                'quality' => $request->input('quality', ''),
                'type' => $request->input('type', ''),
                'network' => $request->input('network', ''),
                'storage' => $request->input('storage', ''),
                'ram' => $request->input('ram', ''),
                'processor' => $request->input('processor', ''),
                'release_year' => $request->input('release_year', ''),
                'carrier_option' => $request->input('carrier_option', ''),
                'sim' => $request->input('sim', ''),
                'battery_health' => $request->input('battery_health', ''),
                'screen_size' => $request->input('screen_size', ''),
            ]);
            $product->specs = array_filter($mergedSpecs, fn($v) => $v !== null && $v !== '');
        }

        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully!',
            'data' => $product,
        ], 200);
    }

    public function updatePosPrice(Request $request)
    {
        $businessId = auth()->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid',
            'variation_id' => 'nullable|uuid',
            'regular_price' => 'required|numeric|gt:0',
            'sale_price' => 'required|numeric|min:0',
            'is_on_sale' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $regularPrice = (float) $request->input('regular_price');
        $salePrice = (float) $request->input('sale_price');
        if ($salePrice > $regularPrice) {
            return response()->json([
                'status' => false,
                'message' => 'Sale price must be less or equal to regular price.',
            ], 422);
        }

        $targetId = $request->input('variation_id') ?: $request->input('id');
        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($targetId);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $product->regular_price = $regularPrice;
        $product->sale_price = $salePrice;
        $product->is_on_sale = $request->boolean('is_on_sale', false);
        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Price updated successfully!',
            'data' => $product,
        ], 200);
    }

    public function getPosProductStockHistory($id)
    {
        $businessId = auth()->id();
        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($id);

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $poItems = PurchaseOrderItem::with(['purchaseOrder.supplier'])
            ->where('product_id', $product->id)
            ->whereHas('purchaseOrder', function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->where('is_deleted', false);
            })
            ->orderByDesc('created_at')
            ->get();

        $isSerializedProduct = (bool) ($product->is_serialized ?? false);

        $purchases = collect();
        foreach ($poItems as $item) {
            $po = $item->purchaseOrder;
            $supplier = optional($po)->supplier;
            $supplierName = optional($supplier)->supplier_name ?: 'Walk-in / Direct';
            $qty = (int) ($item->quantity ?? 0);
            $received = (int) ($item->received_quantity ?? $qty);
            $supplierPayload = $supplier ? [
                'id' => $supplier->id,
                'supplier_name' => $supplier->supplier_name,
                'full_name' => $supplier->full_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'balance' => $supplier->balance,
                'address' => $supplier->address,
            ] : null;

            $rawSerial = trim((string) ($item->imei_serial_no ?? ''));
            $serialList = $isSerializedProduct && $rawSerial !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $rawSerial))))
                : [$rawSerial];
            if (empty($serialList)) {
                $serialList = [''];
            }

            foreach ($serialList as $sIdx => $serialVal) {
                $purchases->push([
                    'id' => count($serialList) > 1 ? "{$item->id}_{$sIdx}" : $item->id,
                    'purchase_order_id' => $item->purchase_order_id,
                    'order_no' => optional($po)->order_no ?: '—',
                    'supplier_name' => $supplierName,
                    'supplier' => $supplierPayload,
                    'quantity' => $qty,
                    'available_qty' => $received > 0 ? $received : $qty,
                    'unit_price' => (float) ($item->unit_cost ?? 0),
                    'pack_price' => (float) ($item->pack_price ?? $item->unit_cost ?? 0),
                    'description' => $item->description ?: 'N/A',
                    'imei_serial_no' => $serialVal,
                    'status' => ($received > 0 || $qty > 0) ? 'AVAILABLE' : 'UNAVAILABLE',
                    'created_at' => $item->created_at ? $item->created_at->toIso8601String() : null,
                ]);
            }
        }
        $purchases = $purchases->values();

        $specs = is_array($product->specs ?? null)
            ? $product->specs
            : (is_string($product->specs ?? null) ? (json_decode($product->specs, true) ?: []) : []);
        $adjustments = is_array($specs['stock_adjustments'] ?? null) ? array_reverse($specs['stock_adjustments']) : [];

        return response()->json([
            'status' => true,
            'data' => [
                'is_serialized' => $isSerializedProduct,
                'reserved_stock' => (int) ($product->stock_quantity ?? 0),
                'purchases' => $purchases,
                'sales' => [],
                'adjustments' => $adjustments,
                'returns' => [],
            ],
        ], 200);
    }

    public function adjustPosProductStock(Request $request)
    {
        $businessId = auth()->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid',
            'quantity_delta' => 'required|integer|not_in:0',
            'reason' => 'nullable|string|max:255',
            'order_no' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($request->input('id'));

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $delta = (int) $request->input('quantity_delta');
        $oldQty = (int) ($product->stock_quantity ?? 0);
        $newQty = max(0, $oldQty + $delta);
        $product->stock_quantity = $newQty;
        $product->product_status_stock = $newQty > 0;

        if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'specs')) {
            $specs = is_array($product->specs ?? null)
                ? $product->specs
                : (is_string($product->specs ?? null) ? (json_decode($product->specs, true) ?: []) : []);
            $history = is_array($specs['stock_adjustments'] ?? null) ? $specs['stock_adjustments'] : [];
            $history[] = [
                'id' => (string) Str::uuid(),
                'order_no' => $request->input('order_no') ?: 'Manual',
                'previous_qty' => $oldQty,
                'delta' => $delta,
                'new_qty' => $newQty,
                'reason' => trim((string) $request->input('reason', 'Stock adjustment')),
                'created_at' => now()->toIso8601String(),
            ];
            $specs['stock_adjustments'] = $history;
            $product->specs = $specs;
        }

        $product->save();

        return response()->json([
            'status' => true,
            'message' => 'Stock adjusted successfully!',
            'data' => ['stock_quantity' => $newQty],
        ], 200);
    }

    public function updatePosBarcode(Request $request)
    {
        $businessId = auth()->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid',
            'product_sku' => 'required|string|max:120',
            'is_default' => 'nullable|boolean',
            'edit_barcode_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($request->input('id'));

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $sku = trim((string) $request->input('product_sku'));
        $duplicate = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->where('id', '!=', $product->id)
            ->where('product_sku', $sku)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status' => false,
                'message' => 'This barcode / SKU already exists on another product.',
            ], 422);
        }

        $specs = is_array($product->specs ?? null)
            ? $product->specs
            : (is_string($product->specs ?? null) ? (json_decode($product->specs, true) ?: []) : []);
        $barcodes = $this->formatProductBarcodes($product);
        $editId = $request->input('edit_barcode_id');
        $wasTargetDefault = false;
        if ($editId) {
            foreach ($barcodes as $b) {
                if ((string) ($b['id'] ?? '') === (string) $editId && !empty($b['is_default'])) {
                    $wasTargetDefault = true;
                    break;
                }
            }
        }
        $isDefault = $request->boolean('is_default', false) || empty($barcodes) || $wasTargetDefault;

        // Prevent duplicate barcode within the same product (unless editing that exact entry)
        foreach ($barcodes as $b) {
            if (strtolower((string) ($b['barcode'] ?? '')) === strtolower($sku) && (string) ($b['id'] ?? '') !== (string) $editId) {
                return response()->json([
                    'status' => false,
                    'message' => 'This barcode already exists for this product.',
                ], 422);
            }
        }

        if ($isDefault) {
            foreach ($barcodes as &$b) {
                $b['is_default'] = false;
            }
            unset($b);
        }

        $nowFormatted = now()->toIso8601String();
        if ($editId) {
            $updated = false;
            foreach ($barcodes as &$b) {
                if ((string) ($b['id'] ?? '') === (string) $editId) {
                    $b['barcode'] = $sku;
                    $b['is_default'] = $isDefault;
                    $b['created_at'] = $nowFormatted;
                    $updated = true;
                    break;
                }
            }
            unset($b);
            if (!$updated) {
                $barcodes[] = [
                    'id' => (string) Str::uuid(),
                    'barcode' => $sku,
                    'is_default' => $isDefault,
                    'created_at' => $nowFormatted,
                ];
            }
        } else {
            $barcodes[] = [
                'id' => (string) Str::uuid(),
                'barcode' => $sku,
                'is_default' => $isDefault,
                'created_at' => $nowFormatted,
            ];
        }

        $specs['barcodes'] = array_values($barcodes);
        $updateData = ['specs' => $specs];
        if ($isDefault) {
            $updateData['product_sku'] = $sku;
        }
        $product->update($updateData);

        return response()->json([
            'status' => true,
            'message' => 'Barcode saved successfully!',
            'data' => $product->fresh(),
            'barcodes' => $specs['barcodes'],
        ]);
    }

    public function deletePosBarcode(Request $request)
    {
        $businessId = auth()->id();
        $validator = Validator::make($request->all(), [
            'id' => 'required|uuid',
            'barcode_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->find($request->input('id'));

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $specs = is_array($product->specs ?? null)
            ? $product->specs
            : (is_string($product->specs ?? null) ? (json_decode($product->specs, true) ?: []) : []);
        $barcodes = $this->formatProductBarcodes($product);
        $barcodeId = (string) $request->input('barcode_id');

        $target = collect($barcodes)->first(fn($b) => (string) ($b['id'] ?? '') === $barcodeId);
        if ($target && !empty($target['is_default'])) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete the default barcode.',
            ], 422);
        }

        $remaining = array_values(array_filter(
            $barcodes,
            fn($b) => (string) ($b['id'] ?? '') !== $barcodeId
        ));
        $specs['barcodes'] = $remaining;
        $product->update(['specs' => $specs]);

        return response()->json([
            'status' => true,
            'message' => 'Barcode deleted successfully!',
            'barcodes' => $remaining,
        ]);
    }
}






