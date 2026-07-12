<?php

namespace App\Http\Controllers\vendor;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PolicyController extends Controller
{
    // ===================== Warranty Policy Logics =======================
    public function warrantyPolicies(Request $request){
        $search = $request->search;
        $perPage = $request->per_page ?? 10;
        $query = Policy::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('type', 1);
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }

        $warranty = $query->latest()->paginate($perPage);
        $from = ($warranty->currentPage() - 1) * $warranty->perPage() + 1;
        $to = min($warranty->currentPage() * $warranty->perPage(), $warranty->total());
        return response()->json([
            'status' => true,
            'message' => 'Warranty policy list retrieved successfully',
            'data' => $warranty->items(),
            'pagination' => [
                'current_page' => $warranty->currentPage(),
                'last_page' => $warranty->lastPage(),
                'per_page' => $warranty->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $warranty->total()
            ]
        ], 200);
    }

    public function createWarrantyPolicy(Request $request){
        $warrantyValidation = Validator::make($request->all(), [
            'title' => [
            'required',
            'string'
        ],
        ]);

        if($warrantyValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Warranty policy validation failed',
                'data' => $warrantyValidation->errors()->all()
            ], 422);
        }

        $warranty = Policy::create([
            'title' => $request->title,
            'description' => $request->description,
            
            'type' => 1,
            'business_id' => auth()->user()->id,
            'approved' => 1
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Warranty added successfully',
            'data' => $warranty
        ], 201);
    }

    public function updateWarrantyPolicy(Request $request){
        $warrantyValidation = Validator::make($request->all(), [
            'title' => [
            'required',
            'string'
        ],
        ]);

        if($warrantyValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Warranty policy validation failed',
                'data' => $warrantyValidation->errors()->all()
            ], 422);
        }
        $warranty = Policy::where('id', $request->id)->first();
        $warranty->update([
            'title' => $request->title,
            'description' => $request->description,
            
            'type' => 1,
            'business_id' => auth()->user()->id,
            'approved' => 1
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Warranty updated successfully',
            'data' => $warranty
        ], 201);
    }


    public function deleteWarranty(Request $request, $id){
        $warranty = Policy::where('id', $id)->first();

        if(!$warranty){
            return response()->json([
                'status' => false,
                'message' => 'Warranty not found'
            ], 404);
        }
        $warranty->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Warranty deleted successfully'
        ], 200);
    }
    // ========================= Return Policy Logics ======================
    public function returnPolicies(Request $request){
        $search = $request->search;
        $perPage = $request->per_page ?? 10;
        $query = Policy::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('type', 2);
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }

        $return = $query->latest()->paginate($perPage);
        $from = ($return->currentPage() - 1) * $return->perPage() + 1;
        $to = min($return->currentPage() * $return->perPage(), $return->total());
        return response()->json([
            'status' => true,
            'message' => 'Return policy list retrieved successfully',
            'data' => $return->items(),
            'pagination' => [
                'current_page' => $return->currentPage(),
                'last_page' => $return->lastPage(),
                'per_page' => $return->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $return->total()
            ]
        ], 200);
    }

    public function createReturnPolicy(Request $request){
        $returnValidation = Validator::make($request->all(), [
            'title' => [
            'required',
            'string'
        ],
        ]);

        if($returnValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Return policy validation failed',
                'data' => $returnValidation->errors()->all()
            ], 422);
        }

        $return = Policy::create([
            'title' => $request->title,
            'description' => $request->description,
            
            'type' => 2,
            'business_id' => auth()->user()->id,
            'approved' => 1
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Return added successfully',
            'data' => $return
        ], 201);
    }

    public function updateReturnPolicy(Request $request){
        $returnValidation = Validator::make($request->all(), [
            'title' => [
            'required',
            'string'
        ],
        ]);

        if($returnValidation->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Return policy validation failed',
                'data' => $returnValidation->errors()->all()
            ], 422);
        }
        $return = Policy::where('id', $request->id)->first();
        $return->update([
            'title' => $request->title,
            'description' => $request->description,
            
            'type' => 2,
            'business_id' => auth()->user()->id,
            'approved' => 1
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Return updated successfully',
            'data' => $return
        ], 201);
    }


    public function deleteReturn(Request $request, $id){
        $return = Policy::where('id', $id)->first();

        if(!$return){
            return response()->json([
                'status' => false,
                'message' => 'Return not found'
            ], 404);
        }
        $return->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Return deleted successfully'
        ], 200);
    }



    // Warranty selection in panel
    public function WarrantySelection(Request $request){
        $search = $request->search;
        $query = Policy::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('approved', 1)->where('type', 1);
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }
        $warranties = $query->latest()->get();
        return response()->json([
            'status' => true,
            'message' => 'Warranties fetched successfully!',
            'data' => $warranties
        ], 200);
    }

    // Return selection in panel
    public function ReturnSelection(Request $request){
        $search = $request->search;
        $query = Policy::where('business_id', auth()->user()->id)->where('is_deleted', false)->where('status', true)->where('type', 2);
        if($search){
            $query->where('title', 'like', "%{$search}%");
        }
        $returns = $query->latest()->get();
        return response()->json([
            'status' => true,
            'message' => 'Returns fetched successfully!',
            'data' => $returns
        ], 200);
    }
}
