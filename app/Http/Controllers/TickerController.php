<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Visit;
use App\Models\Donation;
use App\Enums\RoleEnum;
use App\Enums\VisitStatusEnum;
use App\Enums\DonationStatusEnum;

class TickerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['data' => null]);
        }

        $roleValue = $user->role instanceof RoleEnum ? $user->role->value : $user->role;
        $messages = [];

        if (in_array($roleValue, ['PENGURUS_PANTI', 'KEPALA_PANTI'])) {
            $pendingVisits = Visit::where('status', VisitStatusEnum::PENDING)
                ->get()
                ->filter(fn ($visit) => !$visit->is_expired)
                ->count();
            
            $pendingDonations = Donation::where(function ($query) {
                $query->where('status', DonationStatusEnum::PENDING_DELIVERY->value)
                      ->where(function ($sq) {
                          $sq->whereNull('expires_at')
                             ->orWhere('expires_at', '>=', now());
                      });
            })->orWhere(function ($query) {
                $query->where('status', DonationStatusEnum::PENDING->value)
                      ->where('payment_channel', 'MANUAL');
            })->count();

            if ($pendingVisits > 0) {
                $messages[] = "Terdapat $pendingVisits pengajuan kunjungan menunggu persetujuan.";
            }
            if ($pendingDonations > 0) {
                $messages[] = "Terdapat $pendingDonations donasi menunggu validasi/kedatangan.";
            }
        } else {
            // PENGUNJUNG
            $myPendingVisits = Visit::where('user_id', $user->id)
                ->where('status', VisitStatusEnum::PENDING)
                ->count();
            
            $myPendingDonations = Donation::where('user_id', $user->id)
                ->whereIn('status', [DonationStatusEnum::PENDING->value, DonationStatusEnum::PENDING_DELIVERY->value])
                ->count();

            if ($myPendingVisits > 0) {
                $messages[] = "Anda memiliki $myPendingVisits pengajuan kunjungan yang sedang diproses.";
            }
            if ($myPendingDonations > 0) {
                $messages[] = "Anda memiliki $myPendingDonations donasi yang menunggu validasi.";
            }
        }

        return response()->json([
            'messages' => $messages
        ]);
    }
}
