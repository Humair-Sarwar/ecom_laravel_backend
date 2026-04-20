<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
              ->with(['children' => $activeFilter]);
    };

    $categories = Category::where('business_id', auth()->user()->id)
        ->where('is_active_for_buy', 1)
        ->where('is_deleted', false)
        ->where('level', 0)
        ->with([
            'media',
            'coverMedia',
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
}
