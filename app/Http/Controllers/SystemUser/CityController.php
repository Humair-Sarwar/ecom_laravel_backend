<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Display a listing of cities with search, country filter, and pagination.
     */
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $countryId = $request->input('country_id');
        $perPage = max((int) $request->input('per_page', 10), 1);
        $page = max((int) $request->input('page', 1), 1);

        $query = City::with('country:id,name,iso,currency');

        if (!empty($countryId)) {
            $query->where('country_id', $countryId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('country', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $paginated = $query->orderBy('name', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Cities fetched successfully',
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ], 200);
    }

    /**
     * Store a newly created city.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'status' => 'nullable|boolean',
        ]);

        $city = City::create([
            'name' => $validated['name'],
            'country_id' => $validated['country_id'],
            'status' => $request->boolean('status', true),
        ]);

        $city->load('country:id,name,iso');

        return response()->json([
            'status' => true,
            'message' => 'City created successfully',
            'data' => $city,
        ], 201);
    }

    /**
     * Display the specified city.
     */
    public function show($id)
    {
        $city = City::with('country')->find($id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $city,
        ], 200);
    }

    /**
     * Update the specified city.
     */
    public function update(Request $request, $id)
    {
        $city = City::find($id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'status' => 'nullable|boolean',
        ]);

        $city->update([
            'name' => $validated['name'],
            'country_id' => $validated['country_id'],
            'status' => $request->has('status') ? $request->boolean('status') : $city->status,
        ]);

        $city->load('country:id,name,iso');

        return response()->json([
            'status' => true,
            'message' => 'City updated successfully',
            'data' => $city,
        ], 200);
    }

    /**
     * Toggle city active status.
     */
    public function toggleStatus($id)
    {
        $city = City::find($id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found',
            ], 404);
        }

        $city->status = !$city->status;
        $city->save();

        return response()->json([
            'status' => true,
            'message' => 'City status updated successfully',
            'data' => $city,
        ], 200);
    }

    /**
     * Remove the specified city.
     */
    public function destroy($id)
    {
        $city = City::find($id);

        if (!$city) {
            return response()->json([
                'status' => false,
                'message' => 'City not found',
            ], 404);
        }

        $city->delete();

        return response()->json([
            'status' => true,
            'message' => 'City deleted successfully',
        ], 200);
    }
}
