<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Cuentas;
use Illuminate\Support\Facades\Validator;

class AccountsController extends Controller
{
    /**
     * Obtener todas las cuentas
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request): JsonResponse
    {
        try {
            $accounts = Cuentas::all();

            return response()->json([
                'data' => $accounts,
                'total' => $accounts->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las cuentas'], 500);
        }
    }

    /**
     * Obtener una cuenta específica por ID
     *
     * @param int $accountId
     * @return JsonResponse
     */
    public function getById($accountId): JsonResponse
    {
        try {
            $account = Cuentas::with('movements')->find($accountId);

            if (!$account) {
                return response()->json(['error' => 'Cuenta no encontrada'], 404);
            }

            return response()->json(['data' => $account], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la cuenta'], 500);
        }
    }

    /**
     * Crear una nueva cuenta
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255|unique:accounts,nombre'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $account = new Cuentas();
            $account->nombre = $request->nombre;
            $account->save();

            return response()->json([
                'data' => $account,
                'msg' => 'Cuenta creada correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la cuenta',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una cuenta existente
     *
     * @param Request $request
     * @param int $accountId
     * @return JsonResponse
     */
    public function update(Request $request, $accountId): JsonResponse
    {
        try {
            $account = Cuentas::find($accountId);

            if (!$account) {
                return response()->json(['error' => 'Cuenta no encontrada'], 404);
            }

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255|unique:accounts,nombre,' . $accountId
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $account->nombre = $request->nombre;
            $account->save();

            return response()->json([
                'data' => $account,
                'msg' => 'Cuenta actualizada correctamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la cuenta',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una cuenta
     *
     * @param int $accountId
     * @return JsonResponse
     */
    public function delete($accountId): JsonResponse
    {
        try {
            $account = Cuentas::find($accountId);

            if (!$account) {
                return response()->json(['error' => 'Cuenta no encontrada'], 404);
            }

            // Verificar si la cuenta tiene movimientos asociados
            $movementsCount = $account->movements()->count();
            if ($movementsCount > 0) {
                return response()->json([
                    'error' => 'No se puede eliminar la cuenta porque tiene movimientos asociados',
                    'movements_count' => $movementsCount
                ], 400);
            }

            $account->delete();

            return response()->json([
                'msg' => 'Cuenta eliminada correctamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar la cuenta',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de una cuenta específica
     *
     * @param int $accountId
     * @return JsonResponse
     */
    public function getStats($accountId): JsonResponse
    {
        try {
            $account = Cuentas::find($accountId);

            if (!$account) {
                return response()->json(['error' => 'Cuenta no encontrada'], 404);
            }

            $movements = $account->movements();
            $totalMovements = $movements->count();
            $totalAmountARS = $movements->where('currency', 2)->sum('amount');
            $totalAmountUSD = $movements->where('currency', 1)->sum('amount');

            $stats = [
                'account' => $account,
                'total_movements' => $totalMovements,
                'total_amount_ars' => $totalAmountARS,
                'total_amount_usd' => $totalAmountUSD,
                'movements_by_currency' => [
                    'ARS' => $movements->where('currency', 2)->count(),
                    'USD' => $movements->where('currency', 1)->count()
                ]
            ];

            return response()->json(['data' => $stats], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener las estadísticas de la cuenta',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 