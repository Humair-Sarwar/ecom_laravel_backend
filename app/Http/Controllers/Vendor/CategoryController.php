<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request) {
    $activeFilter = function ($query) use (&$activeFilter) {
        $query->where('is_active_for_buy', 1)
              ->where('is_deleted', false)
              ->with(['parent', 'children' => $activeFilter]);
    };

    $categories = Category::where('business_id', auth()->user()->id)
        ->where('is_active_for_buy', 1)
        ->where('is_deleted', false)
        ->where('level', 0)
        ->with([
            'media',
            'coverMedia',
            'parent',
            'children' => $activeFilter
        ])
        ->get();

    return response()->json([
        'status' => true,
        'message' => 'Categories retrieved successfully!',
        'data' => $categories
    ], 200);
}

    public function createCategory(Request $request){
        $validateCategory = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:50',
            'category_slug' => [
    'required',
    'string',
    'max:255',
    Rule::unique('categories', 'category_slug')
        ->ignore($this->category_id ?? null) 
        ->where(function ($query) {
            return $query->where('is_deleted', 0)
                         ->where('business_id', auth()->user()->id);
        }),
],
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string',
            'cover_image_id' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'page_description' => 'nullable|string',
            'level' => 'nullable|integer',
            'parent_category_id' => 'nullable|exists:categories,id',
        ]);

        if($validateCategory->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateCategory->errors()->all()
            ], 422);
        }

        // Common Data
        $data = [
            'title' => $request->title,
            'category_slug' => $request->category_slug,
            'sort_order' => $request->sort_order,
            'image_id' => $request->image_id,
            'cover_image_id' => $request->cover_image_id,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'page_description' => $request->page_description,
            'business_id' => auth()->id(),
            'is_active_for_buy' => $request->is_active_for_buy ?? 1,
            'is_listing_switch_in_buy' => $request->is_listing_switch_in_buy ?? 1,
            'listing_design' => $request->listing_design ?? 1,
        ];

        // Child Category
        if ($request->parent_category_id) {
            $data['parent_category_id'] = $request->parent_category_id;
            $data['level'] = ($request->level ?? 0) + 1;
        }

        $category = Category::create($data);
        $category->load(['media', 'coverMedia']);

        return response()->json([
            'status' => true,
            'message' => 'Category created successfully!',
            'data' => $category
        ], 201);

    }

    public function editCategory(Request $request, $id){
        $category = Category::where('id', $id)->where('business_id', auth()->user()->id)->with(['media', 'coverMedia'])->first();

        if(!$category){
            return response()->json([
                'status' => false,
                'message' => 'category not found!'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Category found!',
            'data'=> $category
        ], 200);
    }

    public function updateCategory(Request $request){
        $validateCategory = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:50',
            // 'category_slug' => 'required|string|unique:categories,category_slug',
            'category_slug' => [
                'required',
                'string',
                Rule::unique('categories', 'category_slug')
                ->where(function ($query) {
                    $query->where('is_deleted', 0);
                })->ignore($request->id),
            ],
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string',
            'cover_image_id' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'page_description' => 'nullable|string',
            'level' => 'nullable|integer',
            'parent_category_id' => 'nullable|exists:categories,id',
        ]);

        if($validateCategory->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateCategory->errors()->all()
            ], 422);
        }

        // Common Data
        $data = [
            'title' => $request->title,
            'category_slug' => $request->category_slug,
            'sort_order' => $request->sort_order,
            'image_id' => $request->image_id,
            'cover_image_id' => $request->cover_image_id,
            'meta_title' => $request->meta_title,
            'meta_description' => $request->meta_description,
            'page_description' => $request->page_description,
            'business_id' => auth()->id(),
            'is_active_for_buy' => $request->is_active_for_buy ?? 1,
            'is_listing_switch_in_buy' => $request->is_listing_switch_in_buy ?? true,
            'listing_design' => $request->listing_design ?? 1,
            'parent_category_id' => $request->parent_category_id,
            'level' => $request->level ?? 0
        ];

        // Child Category
        if ($request->parent_category_id) {
            $data['parent_category_id'] = $request->parent_category_id;
            $data['level'] = ($request->level ?? 0) + 1;
        }

        $category = Category::where('id', $request->id)
        ->where('business_id', auth()->id())
        ->first();

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $category->update($data);

        $category->load(['media', 'coverMedia']);
        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully!',
            'data' => $category
        ], 200);
    }

    // public function deleteCategory(Request $request, $id){
    //     $category = Category::where('id', $id)->where('business_id', auth()->user()->id)->first();
    //     if(!$category){
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Category not found!'
    //         ], 404);
    //     }
    //     $category->update(['is_deleted' => true]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Category deleted successfully!'
    //     ], 200);

    // }




public function deleteCategory(Request $request, $id) {
    $category = Category::where('id', $id)
        ->where('business_id', auth()->user()->id)
        ->first();

    if (!$category) {
        return response()->json([
            'status' => false,
            'message' => 'Category not found!'
        ], 404);
    }

    try {
        DB::transaction(function () use ($category) {
            $this->deleteCategoryRecursive($category);
        });

        return response()->json([
            'status' => true,
            'message' => 'Category and all its sub-categories deleted successfully!'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to delete category hierarchy',
            'error' => $e->getMessage()
        ], 500);
    }
}


private function deleteCategoryRecursive($category) {
    // 1. Current category ko delete mark karein
    $category->update(['is_deleted' => 1]);

    // 2. Iske direct children find karein (is_deleted 0 wale)
    $children = Category::where('parent_category_id', $category->id)->get();

    // 3. Har child par yahi function dubara chalayein (Recursion)
    foreach ($children as $child) {
        $this->deleteCategoryRecursive($child);
    }
}








    // Categories selection in panel
    public function categories(Request $request)
    {
        $search = $request->search;
        $activeFilter = function ($q) use (&$activeFilter) {
            $q->where('is_active_for_buy', 1)
            ->where('is_deleted', false)
            ->with(['children' => $activeFilter]);
        };

        $query = Category::where('business_id', auth()->user()->id)
            ->where('is_active_for_buy', 1)
            ->where('is_deleted', false)
            ->where('level', 0);

        $query->with([
            'media',
            'coverMedia',
            'children' => $activeFilter
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhereHas('children', function ($q1) use ($search) {
                
                    $q1->where('is_deleted', false)->where('is_active_for_buy', 1)
                    ->where(function($sub) use ($search) {
                        $sub->where('title', 'like', "%{$search}%")
                            ->orWhereHas('children', function ($q2) use ($search) {
                                $q2->where('is_deleted', false)->where('is_active_for_buy', 1)
                                    ->where(function($sub2) use ($search) {
                                        $sub2->where('title', 'like', "%{$search}%")
                                            ->orWhereHas('children', function ($q3) use ($search) {
                                                $q3->where('is_deleted', false)->where('is_active_for_buy', 1)
                                                    ->where('title', 'like', "%{$search}%");
                                            });
                                    });
                            });
                    });
                });
            });
        }

        $categories = $query->orderBy('sort_order')->get();

        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully!',
            'data' => $categories
        ], 200);
    }

    // Categories for POS Bar (First-level categories with recursive active children)
    public function posCategories(Request $request)
    {
        $activeFilter = function ($q) use (&$activeFilter) {
            $q->where('is_active_for_buy', 1)
              ->where('is_deleted', false)
              ->orderBy('sort_order', 'asc')
              ->orderBy('created_at', 'asc')
              ->with(['children' => $activeFilter]);
        };

        $categories = Category::where('business_id', auth()->user()->id)
            ->where('is_active_for_buy', 1)
            ->where('is_deleted', false)
            ->where('level', 0)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->with(['children' => $activeFilter])
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'POS Categories fetched successfully!',
            'data' => $categories
        ], 200);
    }

    // Parent-level categories for Website Home Carousel
    public function websiteCategories(Request $request)
    {
        $categories = Category::where('is_active_for_buy', 1)
            ->where('is_deleted', false)
            ->where('level', 0)
            ->whereNull('parent_category_id')
            ->with(['media', 'coverMedia'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($cat) {
                $mediaPath = optional($cat->media)->media_path
                    ?: optional($cat->coverMedia)->media_path;

                return [
                    'id' => $cat->id,
                    'title' => $cat->title,
                    'name' => $cat->title,
                    'category_slug' => $cat->category_slug,
                    'slug' => $cat->category_slug,
                    'sort_order' => $cat->sort_order,
                    'image' => $mediaPath,
                    'media' => $cat->media,
                    'cover_media' => $cat->coverMedia,
                    'category' => [
                        'id' => $cat->id,
                        'name' => $cat->title,
                        'title' => $cat->title,
                        'slug' => $cat->category_slug,
                        'category_slug' => $cat->category_slug,
                        'image' => $mediaPath,
                    ],
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Website parent categories fetched successfully!',
            'data' => $categories,
            'result' => $categories,
        ], 200);
    }

    // Category details, cover image & child categories for Product Listing Page
    public function websiteCategoryDetails(Request $request)
    {
        $categorySlug = $request->input('category');

        if (!$categorySlug || $categorySlug === 'All') {
            $childCategories = Category::where('is_active_for_buy', 1)
                ->where('is_deleted', false)
                ->where('level', 0)
                ->whereNull('parent_category_id')
                ->with(['media', 'coverMedia'])
                ->orderBy('sort_order', 'asc')
                ->get()
                ->map(function ($child) {
                    $mediaPath = optional($child->media)->media_path
                        ?: optional($child->coverMedia)->media_path;
                    return [
                        'id' => $child->id,
                        'name' => $child->title,
                        'title' => $child->title,
                        'slug' => $child->category_slug,
                        'category_slug' => $child->category_slug,
                        'image' => $mediaPath,
                    ];
                });

            return response()->json([
                'status' => true,
                'parent_category' => [
                    'id' => null,
                    'name' => 'All Categories',
                    'title' => 'All Categories',
                    'slug' => 'All',
                    'category_slug' => 'All',
                    'cover_image' => null,
                    'description' => '',
                ],
                'child_category' => $childCategories,
            ], 200);
        }

        $category = Category::where('is_deleted', false)
            ->where(function ($q) use ($categorySlug) {
                $q->where('category_slug', $categorySlug)
                  ->orWhere('id', $categorySlug);
            })
            ->with(['media', 'coverMedia', 'children.media', 'children.coverMedia'])
            ->first();

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found',
                'parent_category' => null,
                'child_category' => [],
            ], 404);
        }

        $coverImagePath = optional($category->coverMedia)->media_path
            ?: optional($category->media)->media_path;

        $childCategories = $category->children()
            ->where('is_active_for_buy', 1)
            ->where('is_deleted', false)
            ->with(['media', 'coverMedia'])
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function ($child) {
                $mediaPath = optional($child->media)->media_path
                    ?: optional($child->coverMedia)->media_path;

                return [
                    'id' => $child->id,
                    'name' => $child->title,
                    'title' => $child->title,
                    'slug' => $child->category_slug,
                    'category_slug' => $child->category_slug,
                    'image' => $mediaPath,
                ];
            });

        return response()->json([
            'status' => true,
            'parent_category' => [
                'id' => $category->id,
                'name' => $category->title,
                'title' => $category->title,
                'slug' => $category->category_slug,
                'category_slug' => $category->category_slug,
                'cover_image' => $coverImagePath,
                'description' => $category->page_description ?? '',
            ],
            'child_category' => $childCategories,
        ], 200);
    }

    // Products list for Website Category Page (including all inner level categories) with pagination
    public function websiteCategoryProducts(Request $request)
    {
        $categorySlug = $request->input('category');
        $page = max((int) $request->input('page', 1), 1);
        $perPage = max((int) $request->input('per_page', 9), 1);

        $query = Product::where('is_deleted', false)
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
                        ->with(['media', 'variationImage', 'brand', 'category']);
                },
            ]);

        // If a specific category is requested, filter by it and all its inner descendant subcategories
        if ($categorySlug && $categorySlug !== 'All') {
            $rootCategory = Category::where('is_deleted', false)
                ->where(function ($q) use ($categorySlug) {
                    $q->where('category_slug', $categorySlug)
                      ->orWhere('id', $categorySlug);
                })
                ->first();

            if ($rootCategory) {
                $categoryIds = $this->getAllDescendantCategoryIds((string) $rootCategory->id);
                $query->whereIn('category_id', $categoryIds);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'No products found',
                    'data' => [],
                    'products' => [],
                    'total' => 0,
                    'current_page' => $page,
                    'last_page' => 1,
                    'per_page' => $perPage,
                ], 200);
            }
        }

        $paginated = $query
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $formatted = collect($paginated->items())->map(function ($product) {
            $isVariable = (int) ($product->product_type ?? 1) === 2;
            $variations = $product->variations ?? collect();
            $defaultVar = $variations->firstWhere('is_default', true) ?: $variations->first();

            // Determine image
            $imagePath = null;
            if ($product->media && $product->media->isNotEmpty()) {
                $imagePath = $product->media->first()->media_path;
            } elseif ($product->variationImage) {
                $imagePath = $product->variationImage->media_path;
            } elseif ($defaultVar) {
                if ($defaultVar->media && $defaultVar->media->isNotEmpty()) {
                    $imagePath = $defaultVar->media->first()->media_path;
                } elseif ($defaultVar->variationImage) {
                    $imagePath = $defaultVar->variationImage->media_path;
                }
            }

            // Determine prices
            $regularPrice = (float) ($product->regular_price ?? 0);
            $salePrice = (float) ($product->sale_price ?? 0);

            if ($isVariable && $defaultVar) {
                $regularPrice = (float) ($defaultVar->regular_price ?? $regularPrice);
                $salePrice = (float) ($defaultVar->sale_price ?? $salePrice);
            }

            $currentPrice = ($salePrice > 0) ? $salePrice : $regularPrice;
            $originalPrice = ($salePrice > 0 && $regularPrice > $salePrice) ? $regularPrice : null;
            $saveAmount = ($originalPrice && $currentPrice) ? ($originalPrice - $currentPrice) : 0;

            // Brand name
            $brandName = optional($product->brand)->title ?? 'Apple';

            return [
                'id' => $product->id,
                'slug' => $product->product_slug,
                'product_slug' => $product->product_slug,
                'title' => $product->title,
                'name' => $product->title,
                'brand' => $brandName,
                'category_id' => $product->category_id,
                'category_name' => optional($product->category)->title,
                'image' => $imagePath,
                'price' => number_format($currentPrice, 2, '.', ''),
                'regular_price' => $regularPrice > 0 ? number_format($regularPrice, 2, '.', '') : null,
                'sale_price' => $salePrice > 0 ? number_format($salePrice, 2, '.', '') : null,
                'original_price' => $originalPrice ? number_format($originalPrice, 2, '.', '') : null,
                'save_amount' => $saveAmount > 0 ? number_format($saveAmount, 2, '.', '') : null,
                'is_variable' => $isVariable,
                'product_type' => (int) ($product->product_type ?? 1),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully',
            'data' => $formatted,
            'products' => $formatted,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ], 200);
    }

    // Helper to recursively get all descendant category IDs
    protected function getAllDescendantCategoryIds(string $rootCategoryId): array
    {
        $allCategories = Category::where('is_deleted', false)
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
}
