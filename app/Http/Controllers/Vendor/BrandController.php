<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request){
        $search = $request->search;
        $perPage = $request->per_page ?? 10;
        $query = Brand::where('business_id', auth()->user()->id)->where('is_deleted', false)->with('media');
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }

        $brands = $query->orderBy('sort_order')->paginate($perPage);
        $from = ($brands->currentPage() - 1) * $brands->perPage() + 1;
        $to = min($brands->currentPage() * $brands->perPage(), $brands->total());
        return response()->json([
            'status' => true,
            'message' => 'Brand list retrieved successfully',
            'data' => $brands->items(),
            'pagination' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $brands->total()
            ]
        ], 200);
    }

    public function addNewBrand(Request $request){
        $brandValidation = Validator::make($request->all(), [
            'title' => 'required|string|unique:brands,title',
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string',
        ], ['title.unique' => 'The brand title already exists. Please choose a different title.']);

        if($brandValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Brand validation failed',
                'data' => $brandValidation->errors()->all()
            ], 422);
        }

        $brand = Brand::create([
            'title' => $request->title,
            'slug' => $request->title,
            'sort_order' => $request->sort_order,
            'image_id' => $request->image_id,
            'business_id' => auth()->user()->id
        ]);
        $brand->load('media');
        return response()->json([
            'status' => true,
            'message' => 'Brand added successfully',
            'data' => $brand
        ], 201);

    }

    public function editBrand(Request $request, $id){
        $brand = Brand::where('id', $id)->with('media')->first();
        if(!$brand){
            return response()->json([
                'status' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Brand details retrieved successfully',
            'data' => $brand
        ], 200);
    }

    public function updateBrand(Request $request, $id){
        $brandValidation = Validator::make($request->all(), [
            // 'title' => 'required|string|unique:brands,title',
            'title' => [
        'required',
        'string',
        Rule::unique('brands', 'title')
    ->where(fn ($query) => $query->where('is_deleted', 0))
    ->ignore($id),
    ],
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string',
        ]);

        if($brandValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Brand validation failed',
                'data' => $brandValidation->errors()->all()
            ], 422);
        }

        $brand = Brand::where('id', $id)->with('media')->first();
        if(!$brand){
            return response()->json([
                'status' => false,
                'message' => 'Brand not found'
            ], 404);
        }

        $brand->update([
            'title' => $request->title,
            'slug' => $request->title,
            'sort_order' => $request->sort_order,
            'image_id' => $request->image_id,
            'business_id' => auth()->user()->id
        ]);

        $brand->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand
        ], 200);
    }

    public function deleteBrand(Request $request, $id){
        $brand = Brand::where('id', $id)->first();

        if(!$brand){
            return response()->json([
                'status' => false,
                'message' => 'Brand not found'
            ], 404);
        }
        $brand->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Brand deleted successfully'
        ], 200);
    }

    public function DeleteSelectedBrands(Request $request){
        $brands = $request->brand_ids;
        if(!$brands){
            return response()->json([
                'status' => false,
                'message' => 'No brands ids provided!'
            ], 422);
        }


        $deleted = Brand::whereIn('id', $brands)->where('business_id', auth()->user()->id)->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Selected brands deleted!',
            'deleted_count' => $deleted
        ], 200);

    }

    // Brands selection in panel
    public function brands(Request $request){
        $search = $request->search;
        $query = Brand::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('status', true)->with('media');
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }
        $brands = $query->orderBy('sort_order')->get();
        return response()->json([
            'status' => true,
            'message' => 'Brands fetched successfully!',
            'data' => $brands
        ], 200);
    }
    
}
