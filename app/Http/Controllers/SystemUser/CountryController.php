<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a paginated listing of countries with search.
     */
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $perPage = max((int) $request->input('per_page', 10), 1);
        $page = max((int) $request->input('page', 1), 1);

        $query = Country::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nice_name', 'like', "%{$search}%")
                  ->orWhere('iso3', 'like', "%{$search}%")
                  ->orWhere('iso', 'like', "%{$search}%")
                  ->orWhere('phone_code', 'like', "%{$search}%")
                  ->orWhere('currency', 'like', "%{$search}%")
                  ->orWhere('time_zone', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderBy('name', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Countries fetched successfully',
            'data' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ], 200);
    }

    /**
     * Get all active countries for dropdowns.
     */
    public function all()
    {
        $countries = Country::orderBy('name', 'asc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Countries fetched successfully',
            'data' => $countries,
        ], 200);
    }

    /**
     * Store a newly created country.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nice_name' => 'nullable|string|max:255',
            'iso3' => 'required|string|max:10',
            'iso' => 'required|string|max:10',
            'phone_code' => 'required|string|max:20',
            'num_code' => 'required|string|max:20',
            'currency' => 'nullable|string|max:20',
            'time_zone' => 'nullable|string|max:100',
            'status' => 'nullable|boolean',
        ]);

        $country = Country::create([
            'name' => $validated['name'],
            'nice_name' => $validated['nice_name'] ?? $validated['name'],
            'iso3' => strtoupper($validated['iso3']),
            'iso' => strtoupper($validated['iso']),
            'phone_code' => $validated['phone_code'],
            'num_code' => $validated['num_code'],
            'currency' => $validated['currency'] ?? null,
            'time_zone' => $validated['time_zone'] ?? null,
            'status' => $request->boolean('status', true),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Country created successfully',
            'data' => $country,
        ], 201);
    }

    /**
     * Display the specified country.
     */
    public function show($id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json([
                'status' => false,
                'message' => 'Country not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $country,
        ], 200);
    }

    /**
     * Update the specified country.
     */
    public function update(Request $request, $id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json([
                'status' => false,
                'message' => 'Country not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nice_name' => 'nullable|string|max:255',
            'iso3' => 'required|string|max:10',
            'iso' => 'required|string|max:10',
            'phone_code' => 'required|string|max:20',
            'num_code' => 'required|string|max:20',
            'currency' => 'nullable|string|max:20',
            'time_zone' => 'nullable|string|max:100',
            'status' => 'nullable|boolean',
        ]);

        $country->update([
            'name' => $validated['name'],
            'nice_name' => $validated['nice_name'] ?? $country->nice_name,
            'iso3' => strtoupper($validated['iso3']),
            'iso' => strtoupper($validated['iso']),
            'phone_code' => $validated['phone_code'],
            'num_code' => $validated['num_code'],
            'currency' => $validated['currency'] ?? $country->currency,
            'time_zone' => $validated['time_zone'] ?? $country->time_zone,
            'status' => $request->has('status') ? $request->boolean('status') : $country->status,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Country updated successfully',
            'data' => $country,
        ], 200);
    }

    /**
     * Toggle country active status.
     */
    public function toggleStatus($id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json([
                'status' => false,
                'message' => 'Country not found',
            ], 404);
        }

        $country->status = !$country->status;
        $country->save();

        return response()->json([
            'status' => true,
            'message' => 'Country status updated successfully',
            'data' => $country,
        ], 200);
    }

    /**
     * Remove the specified country.
     */
    public function destroy($id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json([
                'status' => false,
                'message' => 'Country not found',
            ], 404);
        }

        $country->delete();

        return response()->json([
            'status' => true,
            'message' => 'Country deleted successfully',
        ], 200);
    }
}
