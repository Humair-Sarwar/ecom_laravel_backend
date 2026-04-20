<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttributeController extends Controller
{
    public function index(Request $request){
        $search = $request->search;
        $perPage = $request->per_page ?? 10;
        $query = Attribute::where('business_id', auth()->user()->id)->where('is_deleted', false)->with(['terms', 'media', 'categories']);

        if($search){
            $query->where('title', 'like', "%{$search}%");
        }

        $attributes = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $from = ($attributes->currentPage() - 1) * $attributes->perPage() + 1;
        $to = min($attributes->currentPage() * $attributes->perPage(), $attributes->total());
        return response()->json([
            'status' => true,
            'message' => 'Attributes retrieved successfully!',
            'data' => $attributes->items(),
            'pagination' => [
                'current_page' => $attributes->currentPage(),
                'last_page' => $attributes->lastPage(),
                'per_page' => $attributes->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $attributes->total()
            ]
        ], 200);
    }

    public function createAttribute(Request $request){
        $validateAttribute = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:300',
            'sub_title' => 'nullable|string',
            'design' => 'required|integer',
            'is_active_for_buy' => 'required',
            'category_ids' => 'required',
            'image_id' => 'nullable|string'
        ]);
        if($validateAttribute->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateAttribute->errors()->all()
            ], 422);
        }
        $attribute = Attribute::create([
            'title' => $request->title,
            'sub_title' => $request->sub_title,
            'description' => $request->description,
            'image_id' => $request->image_id,
            'design' => $request->design,
            'is_active_for_buy' => $request->is_active_for_buy ? 1 : 0,
            'business_id' => auth()->user()->id,
            'attribute_slug' => $request->title,
        ]);
        $attribute->load('media');
        foreach ($request->category_ids as $category_id) {
    DB::table('attribute_term_categories')->insert([
        'attribute_id' => $attribute->id,
        'category_id' => $category_id,
        'business_id' => auth()->id(),
    ]);
}
        return response()->json([
            'status' => true,
            'message' => 'Attribute created successfully!',
            'data' => $attribute
        ], 201);
    }

    public function editAttribute(Request $request, $id){
        $validateAttribute = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:300',
            'sub_title' => 'nullable|string',
            'design' => 'required|integer',
            'purpose' => 'required|integer',
            'category_ids' => 'required',
            'image_id' => 'nullable|string'
        ]);
        if($validateAttribute->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateAttribute->errors()->all()
            ], 422);
        }
        $attribute = Attribute::where('business_id', auth()->id())
    ->where('id', $id)
    ->with('categories')
    ->first();
        if(!$attribute){
            return response()->json([
                'status' => false,
                'message' => 'attribute not found!',

            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'edit attribute retrieved successfully!',
            'data' => $attribute
        ], 200);
    }

    public function updateAttribute(Request $request)
{
    $attribute = Attribute::where('business_id', auth()->id())->where('id', $request->id)
        ->where('id', $request->id)
        ->first();

    if (!$attribute) {
        return response()->json([
            'status' => false,
            'message' => 'Attribute not found!'
        ], 404);
    }

    $attribute->update(['title' => $request->title,
            'sub_title' => $request->sub_title,
            'description' => $request->description,
            'image_id' => $request->image_id,
            'design' => $request->design,
            'is_active_for_buy' => $request->is_active_for_buy ? 1 : 0,
            'business_id' => auth()->user()->id,
            'attribute_slug' => $request->title]);

    // update categories
    if ($request->has('category_ids')) {
        $attribute->categories()->syncWithPivotValues(
    $request->category_ids,
    ['business_id' => auth()->id()]
);
    }

    return response()->json([
        'status' => true,
        'message' => 'Attribute updated successfully!',
        'data' => $attribute->load(['categories', 'media'])
    ], 200);
}
    public function deleteAttribute(Request $request, $id) {
    $attribute = Attribute::where('id', $id)->first();

    if (!$attribute) {
        return response()->json([
            'status' => false,
            'message' => 'Attribute not found'
        ], 404);
    }

    $attribute->update(['is_deleted' => true]);
    $attribute->terms()->update(['is_deleted' => true]);

    return response()->json([
        'status' => true,
        'message' => 'Attribute and its terms deleted successfully!'
    ], 200);
}
}
