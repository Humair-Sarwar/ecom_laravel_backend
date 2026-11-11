<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AccountSettingController extends Controller
{
    private function formatAccountUser(User $user): array
    {
        $profilePath = $user->profile_image ? ltrim((string) $user->profile_image, '/') : null;
        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'gender' => $user->gender ?? '',
            'email' => $user->email ?? '',
            'phone_country_code' => $user->phone_country_code ?: '+44',
            'phone' => $user->phone ?? '',
            'alt_phone_country_code' => $user->alt_phone_country_code ?: '+44',
            'alternative_phone' => $user->alternative_phone ?? '',
            'dob' => $user->dob ? $user->dob->format('Y-m-d') : '',
            'profile_image' => $profilePath,
            'profile_image_url' => $profilePath ? asset('storage/' . $profilePath) : null,
            'auto_submit_eod' => (bool) ($user->auto_submit_eod ?? false),
            'business_name' => $user->business_name ?? '',
        ];
    }

    /**
     * Get authenticated vendor's account settings profile.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'message' => 'Account settings retrieved successfully',
            'data' => $this->formatAccountUser($user),
        ], 200);
    }

    /**
     * Update authenticated vendor's account settings profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'nullable|string|max:40',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_country_code' => 'nullable|string|max:12',
            'phone' => 'nullable|string|max:30',
            'alt_phone_country_code' => 'nullable|string|max:12',
            'alternative_phone' => 'nullable|string|max:30',
            'dob' => 'nullable|date',
            'auto_submit_eod' => 'nullable',
            'remove_profile_image' => 'nullable',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'gender' => $request->filled('gender') ? trim((string) $request->input('gender')) : null,
            'email' => trim((string) $request->input('email')),
            'phone_country_code' => $request->input('phone_country_code') ?: '+44',
            'phone' => $request->filled('phone') ? trim((string) $request->input('phone')) : null,
            'alt_phone_country_code' => $request->input('alt_phone_country_code') ?: '+44',
            'alternative_phone' => $request->filled('alternative_phone') ? trim((string) $request->input('alternative_phone')) : null,
            'dob' => $request->filled('dob') ? $request->input('dob') : null,
            'auto_submit_eod' => filter_var($request->input('auto_submit_eod', false), FILTER_VALIDATE_BOOLEAN),
        ];

        $removeImage = filter_var($request->input('remove_profile_image', false), FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('profile_image')) {
            if (!empty($user->profile_image) && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $updateData['profile_image'] = $request->file('profile_image')->store('avatars', 'public');
        } elseif ($removeImage) {
            if (!empty($user->profile_image) && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $updateData['profile_image'] = null;
        }

        $user->update($updateData);
        $user->refresh();

        return response()->json([
            'status' => true,
            'message' => 'Account settings updated successfully',
            'data' => $this->formatAccountUser($user),
        ], 200);
    }

    /**
     * Change authenticated vendor's password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:5',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check((string) $request->input('current_password'), $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = (string) $request->input('new_password');
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully',
        ], 200);
    }
}
