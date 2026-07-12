<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InventoryService;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Enums\RoleEnum;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Enforce that the authenticated user is a staff member.
     * PENGUNJUNG (donors) must not access catalog management.
     *
     * Called at the top of every protected method.
     */
    private function authorizeStaffRole(Request $request): void
{
    $userRole = $request->user()?->role;
    $roleValue = $userRole instanceof RoleEnum ? $userRole->value : $userRole;
    if (!in_array($roleValue, [RoleEnum::PENGURUS_PANTI->value, RoleEnum::KEPALA_PANTI->value], true)) {
        abort(403, 'Akses ditolak. Hanya PENGURUS atau KEPALA PANTI yang dapat mengelola inventaris.');
    }
}

    /**
     * GET /api/inventories
     * Public endpoint: returns all inventory items for the Smart Cart dropdown.
     * Renamed from index() → publicIndex() because apiResource now owns index()
     * for the /api/kebutuhan resource.
     */
    public function publicIndex()
    {
        try {
            $inventories = $this->inventoryService->getPublicInventoryList();

            return response()->json([
                'status' => 'success',
                'data'   => $inventories,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * GET /api/public/katalog-kebutuhan
     * Public endpoint: returns inventory catalog with target_qty > stock.
     */
    public function getPublicCatalog()
    {
        try {
            $inventories = \App\Models\Inventory::withSum(['itemDonations as virtual_stock' => function ($q) {
                $q->whereHas('donation', function ($d) {
                    $d->where('status', \App\Enums\DonationStatusEnum::PENDING_DELIVERY->value)
                      ->where('expires_at', '>', now());
                });
            }], 'qty')
            ->withSum(['itemDonations as terkumpul_bulan_ini' => function ($q) {
                $q->whereHas('donation', function ($d) {
                    $d->where('status', \App\Enums\DonationStatusEnum::SUCCESS->value);
                })->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
            }], 'qty')
            ->get()
            ->map(function ($item) {
                $item->virtual_stock = (int) $item->virtual_stock;
                $item->terkumpul_bulan_ini = (int) $item->terkumpul_bulan_ini;
                $item->append(['status_kebutuhan', 'is_disabled', 'next_available_date']);
                $item->remaining_need = max(0, $item->target_qty - $item->stock - $item->virtual_stock);
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'data'   => $inventories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memuat katalog kebutuhan.',
            ], 500);
        }
    }

    /**
     * GET /api/kebutuhan
     * Protected: returns full inventory list for the Kelola Kebutuhan dashboard.
     * Includes stock + target_qty so the frontend can compute progress.
     */
    public function index(Request $request)
    {
        $this->authorizeStaffRole($request);

        try {
            $filters = $request->only(['search', 'category', 'status_kebutuhan', 'priority']);
            $items = $this->inventoryService->getAllInventories($filters);

            return response()->json([
                'status' => 'success',
                'data'   => $items,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * POST /api/kebutuhan
     * Protected: create a new inventory catalog entry.
     * stock is managed by the donation check-in flow — never via this endpoint.
     */
    public function store(StoreInventoryRequest $request)
    {
        $this->authorizeStaffRole($request);

        try {
            $inventory = $this->inventoryService->createInventory($request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Kebutuhan berhasil ditambahkan.',
                'data'    => $inventory->toArray(),
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * PUT/PATCH /api/kebutuhan/{id}
     * Protected: update an existing inventory catalog entry.
     * stock field is explicitly excluded in the Service layer.
     */
    public function update(UpdateInventoryRequest $request, string $id)
    {
        $this->authorizeStaffRole($request);

        try {
            $inventory = $this->inventoryService->updateInventory($id, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Kebutuhan berhasil diperbarui.',
                'data'    => $inventory->toArray(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Item inventaris tidak ditemukan.',
            ], 404);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    /**
     * DELETE /api/kebutuhan/{id}
     * Protected: delete an inventory catalog entry.
     */
    public function destroy(Request $request, string $id)
    {
        $this->authorizeStaffRole($request);

        try {
            $this->inventoryService->deleteInventory($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Kebutuhan berhasil dihapus.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Item inventaris tidak ditemukan.',
            ], 404);
        } catch (HttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
