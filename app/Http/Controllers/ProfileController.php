<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ProfileController
 *
 * Handles authenticated user profile self-update.
 * Only `name` and `phone` are mutable — email is immutable (Google Auth).
 */
class ProfileController extends Controller
{
    /**
     * PATCH /api/user/profile
     *
     * Updates the authenticated user's name and/or phone.
     * Returns the full updated user object so the frontend can sync state.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (array_key_exists('phone', $validated)) {
            $updateData['phone'] = $validated['phone'];
        }

        $user->update($updateData);

        // Re-fetch to include all attributes (including phone)
        $user->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil berhasil diperbarui.',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar'=> $user->avatar ? asset('storage/' . $user->avatar) : null,
                'role'  => $user->role instanceof \App\Enums\RoleEnum
                    ? $user->role->value
                    : $user->role,
            ],
        ]);
    }

    /**
     * POST /api/user/avatar
     *
     * Uploads and updates the user's avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => $path]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Foto profil berhasil diperbarui.',
            'avatar'  => $user->avatar ? asset('storage/' . $user->avatar) : null,
        ]);
    }

    /**
     * DELETE /api/user/avatar
     *
     * Removes the user's avatar.
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Foto profil berhasil dihapus.',
        ]);
    }
}
