<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Movements;
use Illuminate\Support\Facades\Validator;
use App\Models\Cuentas;

class MovementsController extends Controller
{
    /**
     * Listado de movimientos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAll(Request $request): JsonResponse
    {
        try {
            $query = Movements::query();

            // Filtros opcionales
            if ($request->has('period')) {
                $query->byPeriod($request->period);
            }

            if ($request->has('currency')) {
                $query->byCurrency($request->currency);
            }

            if ($request->has('course_id')) {
                $query->byCourse($request->course_id);
            }

            if ($request->has('account_id')) {
                $query->byAccount($request->account_id);
            }

            $movements = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'data' => $movements,
                'total' => $movements->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos'], 500);
        }
    }

    /**
     * Obtener un movimiento específico
     *
     * @param int $movementId
     * @return JsonResponse
     */
    public function getById($movementId): JsonResponse
    {
        try {
            $movement = Movements::with('course')->find($movementId);

            if (!$movement) {
                return response()->json(['error' => 'Movimiento no encontrado'], 404);
            }

            return response()->json(['data' => $movement], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el movimiento'], 500);
        }
    }

    /**
     * Crear un nuevo movimiento
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric',
                'currency' => 'required|integer|in:1,2',
                'period' => 'required|string',
                'description' => 'nullable|string|max:500',
                'course_id' => 'nullable|integer|exists:courses,id',
                'account_id' => 'nullable|integer|exists:accounts,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $movement = new Movements();
            $movement->amount = $request->amount;
            $movement->currency = $request->currency;
            $movement->period = $request->period;
            $movement->description = $request->description;
            $movement->course_id = $request->course_id;
            $movement->account_id = $request->account_id;
            $movement->save();

            return response()->json([
                'data' => $movement,
                'msg' => 'Movimiento creado correctamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el movimiento','details' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un movimiento
     *
     * @param Request $request
     * @param int $movementId
     * @return JsonResponse
     */
    public function update(Request $request, $movementId): JsonResponse
    {
        try {
            $movement = Movements::find($movementId);

            if (!$movement) {
                return response()->json(['error' => 'Movimiento no encontrado'], 404);
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|integer|in:1,2',
                'period' => 'required|string',
                'description' => 'nullable|string|max:500',
                'course_id' => 'nullable|integer|exists:courses,id',
                'account_id' => 'nullable|integer|exists:accounts,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $movement->amount = $request->amount;
            $movement->currency = $request->currency;
            $movement->period = $request->period;
            $movement->description = $request->description;
            $movement->course_id = $request->course_id;
            $movement->account_id = $request->account_id;
            $movement->save();

            return response()->json([
                'data' => $movement,
                'msg' => 'Movimiento actualizado correctamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el movimiento'], 500);
        }
    }

    /**
     * Eliminar un movimiento
     *
     * @param int $movementId
     * @return JsonResponse
     */
    public function delete($movementId): JsonResponse
    {
        try {
            $movement = Movements::find($movementId);

            if (!$movement) {
                return response()->json(['error' => 'Movimiento no encontrado'], 404);
            }

            $movement->delete();

            return response()->json([
                'data' => $movement,
                'msg' => 'Movimiento eliminado correctamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el movimiento'], 500);
        }
    }

    /**
     * Obtener movimientos por período (formato mm/yyyy)
     *
     * @param Request $request
     * @param string $period
     * @return JsonResponse
     */
    public function getByPeriod(Request $request, $period): JsonResponse
    {
        try {
            // Validar formato mm/yyyy
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $period)) {
                return response()->json(['error' => 'Formato de período inválido. Use el formato mm/yyyy (ej: 01/2024)'], 400);
            }

            $movements = Movements::byPeriod($period)->get();

            return response()->json([
                'data' => $movements,
                'period' => $period,
                'total' => $movements->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos por período'], 500);
        }
    }

    /**
     * Obtener movimientos por año
     *
     * @param Request $request
     * @param string $year
     * @return JsonResponse
     */
    public function getByYear(Request $request, $year): JsonResponse
    {
        try {
            // Validar formato yyyy
            if (!preg_match('/^\d{4}$/', $year)) {
                return response()->json(['error' => 'Formato de año inválido. Use el formato yyyy (ej: 2024)'], 400);
            }

            $movements = Movements::byYear($year)->get();

            return response()->json([
                'data' => $movements,
                'year' => $year,
                'total' => $movements->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos por año'], 500);
        }
    }

    /**
     * Obtener estadísticas de movimientos con filtros opcionales
     * Devuelve balances de ingresos, egresos y ganancias por moneda
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            // Crear query base con filtros opcionales
            $baseQuery = Movements::query();

            // Aplicar filtros si están presentes
            if ($request->has('course_id') && $request->course_id) {
                $baseQuery->byCourse($request->course_id);
            }

            if ($request->has('period') && $request->period) {
                $baseQuery->byPeriod($request->period);
            }

            if ($request->has('account_id') && $request->account_id) {
                $baseQuery->where('account_id', $request->account_id);
            }

            if ($request->has('currency') && $request->currency) {
                $baseQuery->byCurrency($request->currency);
            }

            // ============ CALCULAR BALANCES CON AMOUNT ============
            // Calcular balances totales por moneda
            $totalBalanceQuery = clone $baseQuery;
            $totalBalances = $totalBalanceQuery->selectRaw('
                currency,
                COUNT(*) as total_movements,
                COUNT(CASE WHEN amount > 0 THEN 1 END) as income_movements,
                COUNT(CASE WHEN amount < 0 THEN 1 END) as outcome_movements,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as outcome
            ')
            ->groupBy('currency')
            ->get();

            // Inicializar balances con valores por defecto
            $balance_ars_income = 0;
            $balance_ars_outcome = 0;
            $balance_usd_income = 0;
            $balance_usd_outcome = 0;
            $balance_ars_total_movements = 0;
            $balance_ars_income_movements = 0;
            $balance_ars_outcome_movements = 0;
            $balance_usd_total_movements = 0;
            $balance_usd_income_movements = 0;
            $balance_usd_outcome_movements = 0;

            // Asignar valores según moneda
            foreach ($totalBalances as $balance) {
                if ($balance->currency == 2) { // ARS
                    $balance_ars_income = $balance->income;
                    $balance_ars_outcome = $balance->outcome;
                    $balance_ars_total_movements = $balance->total_movements;
                    $balance_ars_income_movements = $balance->income_movements;
                    $balance_ars_outcome_movements = $balance->outcome_movements;
                } elseif ($balance->currency == 1) { // USD
                    $balance_usd_income = $balance->income;
                    $balance_usd_outcome = $balance->outcome;
                    $balance_usd_total_movements = $balance->total_movements;
                    $balance_usd_income_movements = $balance->income_movements;
                    $balance_usd_outcome_movements = $balance->outcome_movements;
                }
            }

            // Calcular ganancias
            $balance_ars_profit = $balance_ars_income - $balance_ars_outcome;
            $balance_usd_profit = $balance_usd_income - $balance_usd_outcome;

            // Calcular balances por cuenta
            $accountBalanceQuery = clone $baseQuery;
            $accountBalances = $accountBalanceQuery->selectRaw('
                account_id,
                currency,
                COUNT(*) as total_movements,
                COUNT(CASE WHEN amount > 0 THEN 1 END) as income_movements,
                COUNT(CASE WHEN amount < 0 THEN 1 END) as outcome_movements,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as outcome
            ')
            ->whereNotNull('account_id')
            ->groupBy(['account_id', 'currency'])
            ->get();

            // Agrupar por cuenta y calcular balances
            $byAccounts = [];
            $accountGroups = $accountBalances->groupBy('account_id');

            foreach ($accountGroups as $accountId => $balances) {
                $accountName = Cuentas::where('id', $accountId)->first()->nombre ?? 'Sin cuenta';
                
                $account_ars_income = 0;
                $account_ars_outcome = 0;
                $account_usd_income = 0;
                $account_usd_outcome = 0;
                $account_ars_total_movements = 0;
                $account_ars_income_movements = 0;
                $account_ars_outcome_movements = 0;
                $account_usd_total_movements = 0;
                $account_usd_income_movements = 0;
                $account_usd_outcome_movements = 0;

                foreach ($balances as $balance) {
                    if ($balance->currency == 2) { // ARS
                        $account_ars_income = $balance->income;
                        $account_ars_outcome = $balance->outcome;
                        $account_ars_total_movements = $balance->total_movements;
                        $account_ars_income_movements = $balance->income_movements;
                        $account_ars_outcome_movements = $balance->outcome_movements;
                    } elseif ($balance->currency == 1) { // USD
                        $account_usd_income = $balance->income;
                        $account_usd_outcome = $balance->outcome;
                        $account_usd_total_movements = $balance->total_movements;
                        $account_usd_income_movements = $balance->income_movements;
                        $account_usd_outcome_movements = $balance->outcome_movements;
                    }
                }

                $byAccounts[] = [
                    'account_id' => (int) $accountId,
                    'account_name' => $accountName,
                    'balance_ars_income' => (float) $account_ars_income,
                    'balance_ars_outcome' => (float) $account_ars_outcome,
                    'balance_ars_profit' => (float) ($account_ars_income - $account_ars_outcome),
                    'balance_ars_total_movements' => (int) $account_ars_total_movements,
                    'balance_ars_income_movements' => (int) $account_ars_income_movements,
                    'balance_ars_outcome_movements' => (int) $account_ars_outcome_movements,
                    'balance_usd_income' => (float) $account_usd_income,
                    'balance_usd_outcome' => (float) $account_usd_outcome,
                    'balance_usd_profit' => (float) ($account_usd_income - $account_usd_outcome),
                    'balance_usd_total_movements' => (int) $account_usd_total_movements,
                    'balance_usd_income_movements' => (int) $account_usd_income_movements,
                    'balance_usd_outcome_movements' => (int) $account_usd_outcome_movements
                ];
            }

            // ============ CALCULAR BALANCES CON AMOUNT_NETO ============
            // Calcular balances totales por moneda usando amount_neto
            $totalBalanceNetoQuery = clone $baseQuery;
            $totalBalancesNeto = $totalBalanceNetoQuery->selectRaw('
                currency,
                COUNT(*) as total_movements,
                COUNT(CASE WHEN amount_neto > 0 THEN 1 END) as income_movements,
                COUNT(CASE WHEN amount_neto < 0 THEN 1 END) as outcome_movements,
                SUM(CASE WHEN amount_neto > 0 THEN amount_neto ELSE 0 END) as income,
                SUM(CASE WHEN amount_neto < 0 THEN ABS(amount_neto) ELSE 0 END) as outcome
            ')
            ->groupBy('currency')
            ->get();

            // Inicializar balances neto con valores por defecto
            $balance_neto_ars_income = 0;
            $balance_neto_ars_outcome = 0;
            $balance_neto_usd_income = 0;
            $balance_neto_usd_outcome = 0;
            $balance_neto_ars_total_movements = 0;
            $balance_neto_ars_income_movements = 0;
            $balance_neto_ars_outcome_movements = 0;
            $balance_neto_usd_total_movements = 0;
            $balance_neto_usd_income_movements = 0;
            $balance_neto_usd_outcome_movements = 0;

            // Asignar valores según moneda
            foreach ($totalBalancesNeto as $balance) {
                if ($balance->currency == 2) { // ARS
                    $balance_neto_ars_income = $balance->income;
                    $balance_neto_ars_outcome = $balance->outcome;
                    $balance_neto_ars_total_movements = $balance->total_movements;
                    $balance_neto_ars_income_movements = $balance->income_movements;
                    $balance_neto_ars_outcome_movements = $balance->outcome_movements;
                } elseif ($balance->currency == 1) { // USD
                    $balance_neto_usd_income = $balance->income;
                    $balance_neto_usd_outcome = $balance->outcome;
                    $balance_neto_usd_total_movements = $balance->total_movements;
                    $balance_neto_usd_income_movements = $balance->income_movements;
                    $balance_neto_usd_outcome_movements = $balance->outcome_movements;
                }
            }

            // Calcular ganancias neto
            $balance_neto_ars_profit = $balance_neto_ars_income - $balance_neto_ars_outcome;
            $balance_neto_usd_profit = $balance_neto_usd_income - $balance_neto_usd_outcome;

            // Calcular balances neto por cuenta
            $accountBalanceNetoQuery = clone $baseQuery;
            $accountBalancesNeto = $accountBalanceNetoQuery->selectRaw('
                account_id,
                currency,
                COUNT(*) as total_movements,
                COUNT(CASE WHEN amount_neto > 0 THEN 1 END) as income_movements,
                COUNT(CASE WHEN amount_neto < 0 THEN 1 END) as outcome_movements,
                SUM(CASE WHEN amount_neto > 0 THEN amount_neto ELSE 0 END) as income,
                SUM(CASE WHEN amount_neto < 0 THEN ABS(amount_neto) ELSE 0 END) as outcome
            ')
            ->whereNotNull('account_id')
            ->groupBy(['account_id', 'currency'])
            ->get();

            // Agrupar por cuenta y calcular balances neto
            $byAccountsNeto = [];
            $accountGroupsNeto = $accountBalancesNeto->groupBy('account_id');

            foreach ($accountGroupsNeto as $accountId => $balances) {
                $accountName = Cuentas::where('id', $accountId)->first()->nombre ?? 'Sin cuenta';
                
                $account_neto_ars_income = 0;
                $account_neto_ars_outcome = 0;
                $account_neto_usd_income = 0;
                $account_neto_usd_outcome = 0;
                $account_neto_ars_total_movements = 0;
                $account_neto_ars_income_movements = 0;
                $account_neto_ars_outcome_movements = 0;
                $account_neto_usd_total_movements = 0;
                $account_neto_usd_income_movements = 0;
                $account_neto_usd_outcome_movements = 0;

                foreach ($balances as $balance) {
                    if ($balance->currency == 2) { // ARS
                        $account_neto_ars_income = $balance->income;
                        $account_neto_ars_outcome = $balance->outcome;
                        $account_neto_ars_total_movements = $balance->total_movements;
                        $account_neto_ars_income_movements = $balance->income_movements;
                        $account_neto_ars_outcome_movements = $balance->outcome_movements;
                    } elseif ($balance->currency == 1) { // USD
                        $account_neto_usd_income = $balance->income;
                        $account_neto_usd_outcome = $balance->outcome;
                        $account_neto_usd_total_movements = $balance->total_movements;
                        $account_neto_usd_income_movements = $balance->income_movements;
                        $account_neto_usd_outcome_movements = $balance->outcome_movements;
                    }
                }

                $byAccountsNeto[] = [
                    'account_id' => (int) $accountId,
                    'account_name' => $accountName,
                    'balance_ars_income' => (float) $account_neto_ars_income,
                    'balance_ars_outcome' => (float) $account_neto_ars_outcome,
                    'balance_ars_profit' => (float) ($account_neto_ars_income - $account_neto_ars_outcome),
                    'balance_ars_total_movements' => (int) $account_neto_ars_total_movements,
                    'balance_ars_income_movements' => (int) $account_neto_ars_income_movements,
                    'balance_ars_outcome_movements' => (int) $account_neto_ars_outcome_movements,
                    'balance_usd_income' => (float) $account_neto_usd_income,
                    'balance_usd_outcome' => (float) $account_neto_usd_outcome,
                    'balance_usd_profit' => (float) ($account_neto_usd_income - $account_neto_usd_outcome),
                    'balance_usd_total_movements' => (int) $account_neto_usd_total_movements,
                    'balance_usd_income_movements' => (int) $account_neto_usd_income_movements,
                    'balance_usd_outcome_movements' => (int) $account_neto_usd_outcome_movements
                ];
            }

            return response()->json([
                'bruto' => [
                    'balance_ars_income' => (float) $balance_ars_income,
                    'balance_ars_outcome' => (float) $balance_ars_outcome,
                    'balance_ars_profit' => (float) $balance_ars_profit,
                    'balance_ars_total_movements' => (int) $balance_ars_total_movements,
                    'balance_ars_income_movements' => (int) $balance_ars_income_movements,
                    'balance_ars_outcome_movements' => (int) $balance_ars_outcome_movements,
                    'balance_usd_income' => (float) $balance_usd_income,
                    'balance_usd_outcome' => (float) $balance_usd_outcome,
                    'balance_usd_profit' => (float) $balance_usd_profit,
                    'balance_usd_total_movements' => (int) $balance_usd_total_movements,
                    'balance_usd_income_movements' => (int) $balance_usd_income_movements,
                    'balance_usd_outcome_movements' => (int) $balance_usd_outcome_movements,
                    'by_accounts' => $byAccounts
                ],
                'neto' => [
                    'balance_ars_income' => (float) $balance_neto_ars_income,
                    'balance_ars_outcome' => (float) $balance_neto_ars_outcome,
                    'balance_ars_profit' => (float) $balance_neto_ars_profit,
                    'balance_ars_total_movements' => (int) $balance_neto_ars_total_movements,
                    'balance_ars_income_movements' => (int) $balance_neto_ars_income_movements,
                    'balance_ars_outcome_movements' => (int) $balance_neto_ars_outcome_movements,
                    'balance_usd_income' => (float) $balance_neto_usd_income,
                    'balance_usd_outcome' => (float) $balance_neto_usd_outcome,
                    'balance_usd_profit' => (float) $balance_neto_usd_profit,
                    'balance_usd_total_movements' => (int) $balance_neto_usd_total_movements,
                    'balance_usd_income_movements' => (int) $balance_neto_usd_income_movements,
                    'balance_usd_outcome_movements' => (int) $balance_neto_usd_outcome_movements,
                    'by_accounts' => $byAccountsNeto
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las estadísticas', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener las monedas disponibles
     *
     * @return JsonResponse
     */
    public function getCurrencies(): JsonResponse
    {
        try {
            $currencies = Movements::getAvailableCurrencies();
            
            $formattedCurrencies = [];
            foreach ($currencies as $id => $name) {
                $formattedCurrencies[] = [
                    'id' => $id,
                    'name' => $name
                ];
            }

            return response()->json([
                'data' => $formattedCurrencies
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las monedas'], 500);
        }
    }

    /**
     * Obtener movimientos por curso
     *
     * @param Request $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function getByCourse(Request $request, $courseId): JsonResponse
    {
        try {
            $movements = Movements::with('course')->byCourse($courseId)->get();

            return response()->json([
                'data' => $movements,
                'course_id' => $courseId,
                'total' => $movements->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos por curso'], 500);
        }
    }
} 