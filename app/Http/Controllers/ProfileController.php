<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

        $user->update(array_filter([
            'name'  => $validated['name']  ?? null,
            'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : null,
        ], fn ($v, $k) => $k === 'name' ? $v !== null : true, ARRAY_FILTER_USE_BOTH));

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
                'role'  => $user->role instanceof \App\Enums\RoleEnum
                    ? $user->role->value
                    : $user->role,
            ],
        ]);
    }
}
