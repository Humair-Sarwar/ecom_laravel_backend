<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    /**
     * Resolve or create the vendor's "Series" attribute container.
     */
    protected function getOrCreateSeriesAttribute(string $businessId): Attribute
    {
        $attr = Attribute::where('business_id', $businessId)
            ->where('is_deleted', false)
            ->whereRaw('LOWER(title) = ?', ['series'])
            ->first();

        if ($attr) {
            return $attr;
        }

        return Attribute::create([
            'business_id' => $businessId,
            'title' => 'Series',
            'attribute_slug' => 'series-' . Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_deleted' => false,
        ]);
    }

    public function seriesIndex(Request $request)
    {
        $businessId = (string) auth()->id();
        $seriesAttr = $this->getOrCreateSeriesAttribute($businessId);

        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min(500, (int) $request->query('per_page', 10)));

        $query = Term::where('business_id', $businessId)
            ->where('attribute_id', $seriesAttr->id)
            ->where('is_deleted', false);

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->whereRaw('LOWER(title) LIKE ?', [$like]);
        }

        $terms = $query->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $total = $terms->total();
        $from = $total > 0 ? (($terms->currentPage() - 1) * $terms->perPage() + 1) : 0;
        $to = $total > 0 ? min($terms->currentPage() * $terms->perPage(), $total) : 0;

        return response()->json([
            'status' => true,
            'message' => 'Series fetched successfully!',
            'data' => $terms->items(),
            'pagination' => [
                'current_page' => $terms->currentPage(),
                'last_page' => $terms->lastPage(),
                'per_page' => $terms->perPage(),
                'from' => $from,
                'to' => $to,
                'total' => $total,
            ],
        ], 200);
    }

    public function seriesStore(Request $request)
    {
        $businessId = (string) auth()->id();
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $seriesAttr = $this->getOrCreateSeriesAttribute($businessId);
        $title = trim((string) $request->input('title'));

        $exists = Term::where('business_id', $businessId)
            ->where('attribute_id', $seriesAttr->id)
            ->where('is_deleted', false)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'This series already exists.',
            ], 422);
        }

        $nextOrder = Term::where('business_id', $businessId)
            ->where('attribute_id', $seriesAttr->id)
            ->where('is_deleted', false)
            ->count() + 1;

        $term = Term::create([
            'business_id' => $businessId,
            'attribute_id' => $seriesAttr->id,
            'title' => $title,
            'term_slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
            'sort_order' => $nextOrder,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Series created successfully!',
            'data' => $term,
        ], 201);
    }

    public function seriesUpdate(Request $request, $id)
    {
        $businessId = (string) auth()->id();
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $term = Term::where('business_id', $businessId)
            ->where('id', $id)
            ->where('is_deleted', false)
            ->first();

        if (!$term) {
            return response()->json([
                'status' => false,
                'message' => 'Series not found.',
            ], 404);
        }

        $title = trim((string) $request->input('title'));
        $term->update([
            'title' => $title,
            'term_slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Series updated successfully!',
            'data' => $term->fresh(),
        ], 200);
    }

    public function seriesDestroy($id)
    {
        $businessId = (string) auth()->id();
        $term = Term::where('business_id', $businessId)
            ->where('id', $id)
            ->where('is_deleted', false)
            ->first();

        if (!$term) {
            return response()->json([
                'status' => false,
                'message' => 'Series not found.',
            ], 404);
        }

        $term->update(['is_deleted' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Series deleted successfully!',
        ], 200);
    }
}



