<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TermController extends Controller
{
    public function index(Request $request, $id){
        $search = $request->search;
        $perPage = $request->per_page ?? 10;
        $query = Term::where('business_id', auth()->user()->id)->where('attribute_id', $id)->where('is_deleted', false)->with('media');

        if($search){
            $query->where('title', 'like', "%{$search}%");
        }

        $terms = $query->orderBy('sort_order')->paginate($perPage);
        $from = ($terms->currentPage() - 1) * $terms->perPage() + 1;
        $to = min($terms->currentPage() * $terms->perPage(), $terms->total());
        return response()->json([
            'status' => true,
            'message' => 'Terms retrieved successfully!',
            'data' => $terms->items(),
            'pagination' => [
                'current_page' => $terms->currentPage(),
                'last_page' => $terms->lastPage(),
                'per_page' => $terms->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $terms->total()
            ]
        ], 200);
    }

    public function createTerm(Request $request){
        $validateTerm = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:300',
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string'
        ]);
        if($validateTerm->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateTerm->errors()->all()
            ], 422);
        }
        $term = Term::create([...$request->all(), 'business_id' => auth()->user()->id, 'term_slug' => $request->title]);
        return response()->json([
            'status' => true,
            'message' => 'Term created successfully!',
            'data' => $term
        ], 201);
    }


    public function editTerm(Request $request, $id){
        $term = Term::where('business_id', auth()->user()->id)->where('id', $id)->with('media')->first();
        if(!$term){
            return response()->json([
                'status' => false,
                'message' => 'term not found!',

            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'edit term retrieved successfully!',
            'data' => $term
        ], 200);
    }

    public function updateTerm(Request $request){
        $validateTerm = Validator::make($request->all(), [
            'title' => 'required|string|min:3|max:300',
            'sort_order' => 'required|integer',
            'image_id' => 'nullable|string'
        ]);
        if($validateTerm->fails()){
            return response()->json([
                'status' => false,
                'message' => 'validation error!',
                'error' => $validateTerm->errors()->all()
            ], 422);
        }
        $term =  Term::where('business_id', auth()->user()->id)->where('id', $request->id);
        $data = $term->update([...$request->all(), 'term_slug' => $request->title]);
        // $term->refresh();
        return response()->json([
            'status' => true,
            'message' => 'Term updated successfully!',
            'data' => $term->with('media')->get()
        ], 200);
    }
    public function deleteTerm(Request $request, $id){
        $term = Term::where('id', $id)->first();
        if(!$term){
            return response()->json([
                'status' => false,
                'message' => 'Term not found'
            ], 404);
        }
        $term->update(['is_deleted' => true]);
        return response()->json([
            'status' => true,
            'message' =>'Term deleted successfully!'
        ], 200);
    }
}



