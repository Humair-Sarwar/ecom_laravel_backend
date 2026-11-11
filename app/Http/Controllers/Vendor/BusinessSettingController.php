<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessExpenseType;
use App\Models\BusinessGroup;
use App\Models\BusinessPosOrderProcess;
use App\Models\BusinessPosOrderType;
use App\Models\BusinessPosTerm;
use App\Models\Country;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BusinessSettingController extends Controller
{
    /**
     * Resolve the active business for the authenticated vendor request.
     */
    private function resolveActiveBusiness(Request $request): ?Business
    {
        $activeBiz = $request->attributes->get('current_business');
        if ($activeBiz instanceof Business) {
            return $activeBiz;
        }

        $user = $request->user();
        if (!$user) {
            return null;
        }

        $ownedGroupIds = BusinessGroup::where('owner_id', $user->id)
            ->where('status', 'active')
            ->pluck('id');

        return Business::where(function ($q) use ($user, $ownedGroupIds) {
            $q->where('owner_id', $user->id);
            if ($ownedGroupIds->isNotEmpty()) {
                $q->orWhereIn('business_group_id', $ownedGroupIds);
            }
        })->where('status', 'active')->orderBy('id', 'asc')->first();
    }

    private function formatBusinessGeneral(Business $biz): array
    {
        $logoPath = $biz->logo_image ? ltrim((string) $biz->logo_image, '/') : null;
        $coverPath = $biz->cover_image ? ltrim((string) $biz->cover_image, '/') : null;

        return [
            'id' => $biz->id,
            'name' => $biz->name ?? '',
            'branch_name' => $biz->branch_name ?? '',
            'business_type' => $biz->business_type ?? '',
            'email' => $biz->email ?? '',
            'mobile_country_code' => $biz->mobile_country_code ?: '+44',
            'mobile_no' => $biz->mobile_no ?? '',
            'whatsapp_country_code' => $biz->whatsapp_country_code ?: '+44',
            'whatsapp_no' => $biz->whatsapp_no ?? '',
            'services' => is_array($biz->services) ? $biz->services : [],
            'address' => $biz->address ?? '',
            'city_name' => $biz->city_name ?? '',
            'state_region' => $biz->state_region ?? '',
            'post_code' => $biz->post_code ?? '',
            'country_id' => $biz->country_id,
            'country_name' => $biz->country_name ?? '',
            'currency' => $biz->currency ?: 'GBP',
            'latitude' => $biz->latitude ?? '0',
            'longitude' => $biz->longitude ?? '0',
            'delivery_days' => (int) ($biz->delivery_days ?? 3),
            'delivery_hours' => (int) ($biz->delivery_hours ?? 0),
            'delivery_minutes' => (int) ($biz->delivery_minutes ?? 0),
            'max_shipping_amount' => (float) ($biz->max_shipping_amount ?? 0),
            'shipping_charges' => (float) ($biz->shipping_charges ?? 0),
            'tax_title' => $biz->tax_title ?? 'VAT',
            'tax_value' => (float) ($biz->tax_value ?? 0),
            'vat_registration_no' => $biz->vat_registration_no ?? '',
            'enable_vat_tax' => (bool) ($biz->enable_vat_tax ?? false),
            'disallow_pos_duplicate_cart' => (bool) ($biz->disallow_pos_duplicate_cart ?? false),
            'daily_stock_movement_report' => (bool) ($biz->daily_stock_movement_report ?? false),
            'description' => $biz->description ?? '',
            'logo_image' => $logoPath,
            'logo_image_url' => $logoPath ? asset('storage/' . $logoPath) : null,
            'cover_image' => $coverPath,
            'cover_image_url' => $coverPath ? asset('storage/' . $coverPath) : null,
        ];
    }

    /**
     * Get General Business Settings for active business.
     */
    public function showGeneral(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Active business not found',
            ], 404);
        }

        $countries = Country::orderBy('name')
            ->get(['id', 'name', 'nice_name', 'iso', 'currency', 'phone_code']);

        return response()->json([
            'status' => true,
            'message' => 'Business general settings retrieved successfully',
            'data' => $this->formatBusinessGeneral($business),
            'countries' => $countries,
        ], 200);
    }

    /**
     * Update General Business Settings for active business.
     */
    public function updateGeneral(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Active business not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'business_type' => 'nullable|string|max:120',
            'branch_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:255',
            'mobile_country_code' => 'nullable|string|max:12',
            'mobile_no' => 'nullable|string|max:40',
            'whatsapp_country_code' => 'nullable|string|max:12',
            'whatsapp_no' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:1000',
            'city_name' => 'nullable|string|max:120',
            'state_region' => 'nullable|string|max:120',
            'post_code' => 'nullable|string|max:40',
            'country_id' => 'nullable|integer',
            'country_name' => 'nullable|string|max:120',
            'currency' => 'nullable|string|max:15',
            'latitude' => 'nullable|string|max:40',
            'longitude' => 'nullable|string|max:40',
            'delivery_days' => 'nullable|integer|min:0',
            'delivery_hours' => 'nullable|integer|min:0|max:23',
            'delivery_minutes' => 'nullable|integer|min:0|max:59',
            'max_shipping_amount' => 'nullable|numeric|min:0',
            'shipping_charges' => 'nullable|numeric|min:0',
            'tax_title' => 'nullable|string|max:100',
            'tax_value' => 'nullable|numeric|min:0|max:100',
            'vat_registration_no' => 'nullable|string|max:100',
            'enable_vat_tax' => 'nullable',
            'disallow_pos_duplicate_cart' => 'nullable',
            'daily_stock_movement_report' => 'nullable',
            'description' => 'nullable|string',
            'logo_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'logo_image_id' => 'nullable|string',
            'logo_media_path' => 'nullable|string',
            'cover_image_id' => 'nullable|string',
            'cover_media_path' => 'nullable|string',
            'remove_logo_image' => 'nullable',
            'remove_cover_image' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $countryId = $request->filled('country_id') ? (int) $request->input('country_id') : $business->country_id;
        $countryName = $request->input('country_name');
        if ($countryId && empty($countryName)) {
            $country = Country::find($countryId);
            if ($country) {
                $countryName = $country->nice_name ?: $country->name;
            }
        }

        $updateData = [
            'business_type' => $request->input('business_type'),
            'branch_name' => $request->input('branch_name'),
            'email' => $request->input('email'),
            'mobile_country_code' => $request->input('mobile_country_code') ?: '+44',
            'mobile_no' => $request->input('mobile_no'),
            'whatsapp_country_code' => $request->input('whatsapp_country_code') ?: '+44',
            'whatsapp_no' => $request->input('whatsapp_no'),
            'address' => $request->input('address'),
            'city_name' => $request->input('city_name'),
            'state_region' => $request->input('state_region'),
            'post_code' => $request->input('post_code'),
            'country_id' => $countryId,
            'country_name' => $countryName ?? $business->country_name,
            'currency' => $request->input('currency') ?: ($business->currency ?: 'GBP'),
            'latitude' => $request->input('latitude', '0'),
            'longitude' => $request->input('longitude', '0'),
            'delivery_days' => (int) $request->input('delivery_days', 0),
            'delivery_hours' => (int) $request->input('delivery_hours', 0),
            'delivery_minutes' => (int) $request->input('delivery_minutes', 0),
            'max_shipping_amount' => (float) $request->input('max_shipping_amount', 0),
            'shipping_charges' => (float) $request->input('shipping_charges', 0),
            'tax_title' => $request->input('tax_title'),
            'tax_value' => (float) $request->input('tax_value', 0),
            'vat_registration_no' => $request->input('vat_registration_no'),
            'enable_vat_tax' => filter_var($request->input('enable_vat_tax', false), FILTER_VALIDATE_BOOLEAN),
            'disallow_pos_duplicate_cart' => filter_var($request->input('disallow_pos_duplicate_cart', false), FILTER_VALIDATE_BOOLEAN),
            'daily_stock_movement_report' => filter_var($request->input('daily_stock_movement_report', false), FILTER_VALIDATE_BOOLEAN),
            'description' => $request->input('description'),
        ];

        $removeLogo = filter_var($request->input('remove_logo_image', false), FILTER_VALIDATE_BOOLEAN);
        if ($request->hasFile('logo_image')) {
            if (!empty($business->logo_image) && str_starts_with($business->logo_image, 'business/logos/') && Storage::disk('public')->exists($business->logo_image)) {
                Storage::disk('public')->delete($business->logo_image);
            }
            $updateData['logo_image'] = $request->file('logo_image')->store('business/logos', 'public');
        } elseif ($request->filled('logo_image_id') || $request->filled('logo_media_path')) {
            $media = $request->filled('logo_image_id') ? Media::find($request->input('logo_image_id')) : null;
            $pickedPath = $media ? $media->media_path : ltrim((string) $request->input('logo_media_path'), '/');
            if ($pickedPath) {
                $updateData['logo_image'] = ltrim($pickedPath, '/');
            }
        } elseif ($removeLogo) {
            if (!empty($business->logo_image) && str_starts_with($business->logo_image, 'business/logos/') && Storage::disk('public')->exists($business->logo_image)) {
                Storage::disk('public')->delete($business->logo_image);
            }
            $updateData['logo_image'] = null;
        }

        $removeCover = filter_var($request->input('remove_cover_image', false), FILTER_VALIDATE_BOOLEAN);
        if ($request->hasFile('cover_image')) {
            if (!empty($business->cover_image) && str_starts_with($business->cover_image, 'business/covers/') && Storage::disk('public')->exists($business->cover_image)) {
                Storage::disk('public')->delete($business->cover_image);
            }
            $updateData['cover_image'] = $request->file('cover_image')->store('business/covers', 'public');
        } elseif ($request->filled('cover_image_id') || $request->filled('cover_media_path')) {
            $media = $request->filled('cover_image_id') ? Media::find($request->input('cover_image_id')) : null;
            $pickedPath = $media ? $media->media_path : ltrim((string) $request->input('cover_media_path'), '/');
            if ($pickedPath) {
                $updateData['cover_image'] = ltrim($pickedPath, '/');
            }
        } elseif ($removeCover) {
            if (!empty($business->cover_image) && str_starts_with($business->cover_image, 'business/covers/') && Storage::disk('public')->exists($business->cover_image)) {
                Storage::disk('public')->delete($business->cover_image);
            }
            $updateData['cover_image'] = null;
        }

        $business->update($updateData);
        $business->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Business settings updated successfully',
            'data' => $this->formatBusinessGeneral($business),
        ], 200);
    }

    /**
     * List POS Terms & Conditions for the active business.
     */
    public function posTerms(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $terms = BusinessPosTerm::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Terms and conditions retrieved successfully',
            'data' => $terms,
        ], 200);
    }

    /**
     * Create a new POS Term & Condition for the active business.
     */
    public function storePosTerm(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'section' => 'required|string|in:Order,Order Item,Repair Work',
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($business->name ?: ($user->email ?? 'Vendor'));

        $term = BusinessPosTerm::create([
            'business_id' => $business->id,
            'section' => $request->input('section'),
            'title' => trim((string) $request->input('title')),
            'note' => $request->filled('note') ? trim((string) $request->input('note')) : null,
            'is_default' => false,
            'created_by' => $user?->id,
            'created_by_name' => $creatorName,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Terms and conditions created successfully',
            'data' => $term,
        ], 201);
    }

    /**
     * Update an existing POS Term & Condition.
     */
    public function updatePosTerm(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $term = BusinessPosTerm::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$term) {
            return response()->json(['status' => false, 'message' => 'Term not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'section' => 'required|string|in:Order,Order Item,Repair Work',
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $term->update([
            'section' => $request->input('section'),
            'title' => trim((string) $request->input('title')),
            'note' => $request->filled('note') ? trim((string) $request->input('note')) : null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Terms and conditions updated successfully',
            'data' => $term->fresh(),
        ], 200);
    }

    /**
     * Toggle default status for a POS Term & Condition (scoped per section).
     */
    public function toggleDefaultPosTerm(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $term = BusinessPosTerm::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$term) {
            return response()->json(['status' => false, 'message' => 'Term not found'], 404);
        }

        $nextDefault = !$term->is_default;
        if ($nextDefault) {
            BusinessPosTerm::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->where('id', '!=', $term->id)
                ->update(['is_default' => false]);
        }

        $term->is_default = $nextDefault;
        $term->save();

        return response()->json([
            'status' => true,
            'message' => $nextDefault ? 'Marked as default' : 'Removed default status',
            'data' => $term,
        ], 200);
    }

    /**
     * Soft-delete a POS Term & Condition.
     */
    public function deletePosTerm(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $term = BusinessPosTerm::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$term) {
            return response()->json(['status' => false, 'message' => 'Term not found'], 404);
        }

        $term->is_deleted = true;
        $term->is_default = false;
        $term->save();

        return response()->json([
            'status' => true,
            'message' => 'Terms and conditions deleted successfully',
        ], 200);
    }

    /**
     * Build recursive tree from flat collection of order types.
     */
    private function buildOrderTypeTree($allItems, $parentId = null): array
    {
        $branch = [];
        foreach ($allItems as $item) {
            $itemParent = $item->parent_id ?: null;
            if ($itemParent === $parentId) {
                $row = $item->toArray();
                $row['children'] = $this->buildOrderTypeTree($allItems, $item->id);
                $branch[] = $row;
            }
        }
        return $branch;
    }

    private function collectDescendantIds($allItems, string $rootId): array
    {
        $ids = [];
        foreach ($allItems as $item) {
            if ($item->parent_id === $rootId) {
                $ids[] = $item->id;
                $ids = array_merge($ids, $this->collectDescendantIds($allItems, $item->id));
            }
        }
        return $ids;
    }

    /**
     * List POS Order Types (tree + flat list) for active business.
     */
    public function posOrderTypes(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $all = BusinessPosOrderType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Order types retrieved successfully',
            'data' => $this->buildOrderTypeTree($all, null),
            'flat' => $all,
        ], 200);
    }

    /**
     * Create a new POS Order Type.
     */
    public function storePosOrderType(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'parent_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $parentId = $request->filled('parent_id') ? $request->input('parent_id') : null;
        if ($parentId) {
            $parentExists = BusinessPosOrderType::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->whereNull('parent_id')
                ->where('id', $parentId)
                ->exists();
            if (!$parentExists) {
                return response()->json(['status' => false, 'message' => 'Parent must be a level 1 order type'], 422);
            }
        }

        $user = $request->user();
        $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($business->name ?: ($user->email ?? 'Vendor'));

        $orderType = BusinessPosOrderType::create([
            'business_id' => $business->id,
            'parent_id' => $parentId,
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'is_default' => false,
            'created_by' => $user?->id,
            'created_by_name' => $creatorName,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order type created successfully',
            'data' => $orderType,
        ], 201);
    }

    /**
     * Update an existing POS Order Type.
     */
    public function updatePosOrderType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $orderType = BusinessPosOrderType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$orderType) {
            return response()->json(['status' => false, 'message' => 'Order type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'parent_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $parentId = $request->filled('parent_id') ? $request->input('parent_id') : null;
        if ($parentId) {
            if ($parentId === $orderType->id) {
                return response()->json(['status' => false, 'message' => 'An order type cannot be its own parent'], 422);
            }

            $all = BusinessPosOrderType::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->get();
            $descendants = $this->collectDescendantIds($all, $orderType->id);
            if (in_array($parentId, $descendants, true)) {
                return response()->json(['status' => false, 'message' => 'Cannot move an order type under its own child'], 422);
            }

            $parentRecord = $all->firstWhere('id', $parentId);
            if (!$parentRecord || !empty($parentRecord->parent_id)) {
                return response()->json(['status' => false, 'message' => 'Parent must be a level 1 order type'], 422);
            }
        }

        $orderType->update([
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'parent_id' => $parentId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order type updated successfully',
            'data' => $orderType->fresh(),
        ], 200);
    }

    /**
     * Toggle default status for a POS Order Type (only 1 default allowed per business).
     */
    public function toggleDefaultPosOrderType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $orderType = BusinessPosOrderType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$orderType) {
            return response()->json(['status' => false, 'message' => 'Order type not found'], 404);
        }

        $nextDefault = !$orderType->is_default;
        if ($nextDefault) {
            BusinessPosOrderType::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->where('id', '!=', $orderType->id)
                ->update(['is_default' => false]);
        }

        $orderType->is_default = $nextDefault;
        $orderType->save();

        return response()->json([
            'status' => true,
            'message' => $nextDefault ? 'Order type set as default' : 'Removed default order type',
            'data' => $orderType,
        ], 200);
    }

    /**
     * Soft-delete a POS Order Type (and its nested children).
     */
    public function deletePosOrderType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $orderType = BusinessPosOrderType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$orderType) {
            return response()->json(['status' => false, 'message' => 'Order type not found'], 404);
        }

        $all = BusinessPosOrderType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->get();
        $idsToDelete = array_merge([$orderType->id], $this->collectDescendantIds($all, $orderType->id));

        BusinessPosOrderType::where('business_id', $business->id)
            ->whereIn('id', $idsToDelete)
            ->update(['is_deleted' => true, 'is_default' => false]);

        return response()->json([
            'status' => true,
            'message' => 'Order type deleted successfully',
        ], 200);
    }

    /**
     * List POS Order Processes for the active business.
     */
    public function posOrderProcesses(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $processes = BusinessPosOrderProcess::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Order processes retrieved successfully',
            'data' => $processes,
        ], 200);
    }

    /**
     * Create a new POS Order Process.
     */
    public function storePosOrderProcess(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDefault = filter_var($request->input('is_default', false), FILTER_VALIDATE_BOOLEAN);
        if ($isDefault) {
            BusinessPosOrderProcess::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->update(['is_default' => false]);
        }

        $user = $request->user();
        $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($business->name ?: ($user->email ?? 'Vendor'));

        $process = BusinessPosOrderProcess::create([
            'business_id' => $business->id,
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'is_default' => $isDefault,
            'created_by' => $user?->id,
            'created_by_name' => $creatorName,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order process created successfully',
            'data' => $process,
        ], 201);
    }

    /**
     * Update an existing POS Order Process.
     */
    public function updatePosOrderProcess(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $process = BusinessPosOrderProcess::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$process) {
            return response()->json(['status' => false, 'message' => 'Order process not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDefault = $request->has('is_default')
            ? filter_var($request->input('is_default'), FILTER_VALIDATE_BOOLEAN)
            : $process->is_default;

        if ($isDefault) {
            BusinessPosOrderProcess::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->where('id', '!=', $process->id)
                ->update(['is_default' => false]);
        }

        $process->update([
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order process updated successfully',
            'data' => $process->fresh(),
        ], 200);
    }

    /**
     * Toggle default status for a POS Order Process (only 1 default allowed per business).
     */
    public function toggleDefaultPosOrderProcess(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $process = BusinessPosOrderProcess::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$process) {
            return response()->json(['status' => false, 'message' => 'Order process not found'], 404);
        }

        $nextDefault = !$process->is_default;
        if ($nextDefault) {
            BusinessPosOrderProcess::where('business_id', $business->id)
                ->where('is_deleted', false)
                ->where('id', '!=', $process->id)
                ->update(['is_default' => false]);
        }

        $process->is_default = $nextDefault;
        $process->save();

        return response()->json([
            'status' => true,
            'message' => $nextDefault ? 'Order process set as default' : 'Removed default order process',
            'data' => $process,
        ], 200);
    }

    /**
     * Soft-delete a POS Order Process.
     */
    public function deletePosOrderProcess(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $process = BusinessPosOrderProcess::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$process) {
            return response()->json(['status' => false, 'message' => 'Order process not found'], 404);
        }

        $process->is_deleted = true;
        $process->is_default = false;
        $process->save();

        return response()->json([
            'status' => true,
            'message' => 'Order process deleted successfully',
        ], 200);
    }

    /**
     * List Expense Types (daily / business) for active business.
     */
    public function expenseTypes(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $category = $request->query('category', 'daily');
        if (!in_array($category, ['daily', 'business'], true)) {
            $category = 'daily';
        }

        $items = BusinessExpenseType::where('business_id', $business->id)
            ->where('expense_category', $category)
            ->where('is_deleted', false)
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Expense types retrieved successfully',
            'data' => $items,
        ], 200);
    }

    /**
     * Create a new Expense Type.
     */
    public function storeExpenseType(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'expense_category' => 'nullable|string|in:daily,business',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = $request->input('expense_category', 'daily');
        $isDefault = filter_var($request->input('is_default', false), FILTER_VALIDATE_BOOLEAN);

        if ($isDefault) {
            BusinessExpenseType::where('business_id', $business->id)
                ->where('expense_category', $category)
                ->where('is_deleted', false)
                ->update(['is_default' => false]);
        }

        $user = $request->user();
        $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($business->name ?: ($user->email ?? 'Vendor'));

        $expenseType = BusinessExpenseType::create([
            'business_id' => $business->id,
            'expense_category' => $category,
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'is_default' => $isDefault,
            'created_by' => $user?->id,
            'created_by_name' => $creatorName,
            'is_deleted' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Expense type created successfully',
            'data' => $expenseType,
        ], 201);
    }

    /**
     * Update an existing Expense Type.
     */
    public function updateExpenseType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $expenseType = BusinessExpenseType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$expenseType) {
            return response()->json(['status' => false, 'message' => 'Expense type not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:1',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDefault = $request->has('is_default')
            ? filter_var($request->input('is_default'), FILTER_VALIDATE_BOOLEAN)
            : $expenseType->is_default;

        if ($isDefault) {
            BusinessExpenseType::where('business_id', $business->id)
                ->where('expense_category', $expenseType->expense_category)
                ->where('is_deleted', false)
                ->where('id', '!=', $expenseType->id)
                ->update(['is_default' => false]);
        }

        $expenseType->update([
            'name' => trim((string) $request->input('name')),
            'sort_order' => (int) $request->input('sort_order', 1),
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Expense type updated successfully',
            'data' => $expenseType->fresh(),
        ], 200);
    }

    /**
     * Toggle default Expense Type (only 1 default allowed per category per business).
     */
    public function toggleDefaultExpenseType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $expenseType = BusinessExpenseType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$expenseType) {
            return response()->json(['status' => false, 'message' => 'Expense type not found'], 404);
        }

        $nextDefault = !$expenseType->is_default;
        if ($nextDefault) {
            BusinessExpenseType::where('business_id', $business->id)
                ->where('expense_category', $expenseType->expense_category)
                ->where('is_deleted', false)
                ->where('id', '!=', $expenseType->id)
                ->update(['is_default' => false]);
        }

        $expenseType->is_default = $nextDefault;
        $expenseType->save();

        return response()->json([
            'status' => true,
            'message' => $nextDefault ? 'Expense type set as default' : 'Removed default expense type',
            'data' => $expenseType,
        ], 200);
    }

    /**
     * Soft-delete an Expense Type.
     */
    public function deleteExpenseType(Request $request, $id)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $expenseType = BusinessExpenseType::where('business_id', $business->id)
            ->where('is_deleted', false)
            ->find($id);

        if (!$expenseType) {
            return response()->json(['status' => false, 'message' => 'Expense type not found'], 404);
        }

        $expenseType->is_deleted = true;
        $expenseType->is_default = false;
        $expenseType->save();

        return response()->json([
            'status' => true,
            'message' => 'Expense type deleted successfully',
        ], 200);
    }

    private function formatEmailSettings(Business $biz): array
    {
        $decodedPassword = '';
        if (!empty($biz->smtp_password)) {
            try {
                $decodedPassword = Crypt::decryptString($biz->smtp_password);
            } catch (\Throwable $e) {
                $decodedPassword = (string) $biz->smtp_password;
            }
        }

        return [
            'smtp_active' => (bool) ($biz->smtp_active ?? false),
            'smtp_port' => $biz->smtp_port ?? '',
            'smtp_host' => $biz->smtp_host ?? '',
            'smtp_email' => $biz->smtp_email ?? '',
            'smtp_password' => $decodedPassword,
            'stock_alert_after_sale' => (bool) ($biz->stock_alert_after_sale ?? false),
            'stock_alert_eod_list' => (bool) ($biz->stock_alert_eod_list ?? false),
        ];
    }

    /**
     * Get Email & Stock Alert settings for active business.
     */
    public function showEmailSettings(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Email settings retrieved successfully',
            'data' => $this->formatEmailSettings($business),
        ], 200);
    }

    /**
     * Update Email & Stock Alert settings for active business.
     */
    public function updateEmailSettings(Request $request)
    {
        $business = $this->resolveActiveBusiness($request);
        if (!$business) {
            return response()->json(['status' => false, 'message' => 'Active business not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'smtp_active' => 'nullable|boolean',
            'smtp_port' => 'nullable|string|max:20',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_email' => 'nullable|email|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'stock_alert_after_sale' => 'nullable|boolean',
            'stock_alert_eod_list' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];

        if ($request->has('smtp_active')) {
            $updateData['smtp_active'] = filter_var($request->input('smtp_active'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('smtp_port')) {
            $updateData['smtp_port'] = $request->filled('smtp_port') ? trim((string) $request->input('smtp_port')) : null;
        }
        if ($request->has('smtp_host')) {
            $updateData['smtp_host'] = $request->filled('smtp_host') ? trim((string) $request->input('smtp_host')) : null;
        }
        if ($request->has('smtp_email')) {
            $updateData['smtp_email'] = $request->filled('smtp_email') ? trim((string) $request->input('smtp_email')) : null;
        }
        if ($request->has('smtp_password')) {
            $rawPass = (string) $request->input('smtp_password', '');
            $updateData['smtp_password'] = $rawPass !== '' ? Crypt::encryptString($rawPass) : null;
        }
        if ($request->has('stock_alert_after_sale')) {
            $updateData['stock_alert_after_sale'] = filter_var($request->input('stock_alert_after_sale'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('stock_alert_eod_list')) {
            $updateData['stock_alert_eod_list'] = filter_var($request->input('stock_alert_eod_list'), FILTER_VALIDATE_BOOLEAN);
        }

        $business->update($updateData);
        $business->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Email settings updated successfully',
            'data' => $this->formatEmailSettings($business),
        ], 200);
    }
}
