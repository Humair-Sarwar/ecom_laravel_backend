<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request){
        $categories = Category::where('business_id', auth()->user()->id)->where('purpose', 1)->where('is_deleted', false)->where('level', 0)->with(['children', 'media', 'coverMedia'])->get();
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
                Rule::unique('categories', 'category_slug')
                ->where(function ($query) {
                    $query->where('is_deleted', 0);
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
            'purpose' => $request->purpose ?? 1,
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
            'purpose' => $request->purpose ?? 1,
            'is_listing_switch_in_buy' => $request->is_listing_switch_in_buy ?? true,
            'listing_design' => $request->listing_design ?? 1,
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

    public function deleteCategory(Request $request, $id){
        $category = Category::where('id', $id)->where('business_id', auth()->user()->id)->first();
        if(!$category){
            return response()->json([
                'status' => false,
                'message' => 'Category not found!'
            ], 404);
        }
        $category->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully!'
        ], 200);

    }

    // Categories selection in panel
    public function categories(Request $request){
        $search = $request->search;
        $query = Category::where('business_id', auth()->user()->id)->where('is_deleted', false)->with(['children', 'media', 'coverMedia']);
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }
        $categories = $query->orderBy('sort_order')->get();
        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully!',
            'data' => $categories
        ], 200);
    }
}
