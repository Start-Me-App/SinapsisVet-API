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
    public function getAll(Request $request)
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

            // Incluir relaciones para el CSV
            $movements = $query->with(['course', 'account'])
                              ->orderBy('created_at', 'desc')
                              ->get();

            // Si se solicita descarga como CSV
            if ($request->has('download') && $request->download == 1) {
                return $this->generateCsv($movements);
            }

            return response()->json([
                'data' => $movements,
                'total' => $movements->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los movimientos'], 500);
        }
    }

    /**
     * Generar CSV de movimientos
     *
     * @param $movements
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function generateCsv($movements)
    {
        $filename = 'movimientos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($movements) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8 (para que Excel abra correctamente los caracteres especiales)
            fwrite($file, "\xEF\xBB\xBF");
            
            // Encabezados del CSV
            fputcsv($file, [
                'ID',
                'Monto Bruto',
                'Monto Neto',
                'Moneda',
                'Período',
                'Descripción',
                'Curso',
                'Cuenta'
            ], ';');

            // Datos
            foreach ($movements as $movement) {
                fputcsv($file, [
                    $movement->id,
                    $movement->amount,
                    $movement->amount_neto ?? '',
                    $movement->currency_name,
                    $movement->period,
                    $movement->description ?? '',
                    $movement->course->title ?? '',
                    $movement->account->nombre ?? ''
                ], ';');
            }

            fclose($file);
        }, $filename, $headers);
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
            $movement->amount_neto = $request->amount_neto;
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
                'amount_neto' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Datos inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            $movement->amount_neto = $request->amount_neto;
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

            // ============ CALCULAR GANANCIAS ============
            // Calcular ganancias totales por moneda usando amount_neto y courses.comission
            $totalGananciasQuery = clone $baseQuery;
            $totalGanancias = $totalGananciasQuery
                ->join('courses', 'movements.course_id', '=', 'courses.id')
                ->selectRaw('
                    movements.currency,
                    COUNT(*) as total_movements,
                    COUNT(CASE WHEN movements.amount_neto > 0 THEN 1 END) as income_movements,
                    SUM(CASE 
                        WHEN movements.amount_neto > 0 
                        THEN movements.amount_neto * (courses.comission / 100) 
                        ELSE 0 
                    END) as total_ganancia
                ')
                ->whereNotNull('movements.course_id')
                ->whereNotNull('courses.comission')
                ->groupBy('movements.currency')
                ->get();

            // Inicializar ganancias con valores por defecto
            $ganancia_ars_total = 0;
            $ganancia_ars_total_movements = 0;
            $ganancia_ars_income_movements = 0;
            $ganancia_usd_total = 0;
            $ganancia_usd_total_movements = 0;
            $ganancia_usd_income_movements = 0;

            // Asignar valores según moneda
            foreach ($totalGanancias as $ganancia) {
                if ($ganancia->currency == 2) { // ARS
                    $ganancia_ars_total = $ganancia->total_ganancia;
                    $ganancia_ars_total_movements = $ganancia->total_movements;
                    $ganancia_ars_income_movements = $ganancia->income_movements;
                } elseif ($ganancia->currency == 1) { // USD
                    $ganancia_usd_total = $ganancia->total_ganancia;
                    $ganancia_usd_total_movements = $ganancia->total_movements;
                    $ganancia_usd_income_movements = $ganancia->income_movements;
                }
            }

            // Calcular ganancias por cuenta
            $accountGananciasQuery = clone $baseQuery;
            $accountGanancias = $accountGananciasQuery
                ->join('courses', 'movements.course_id', '=', 'courses.id')
                ->selectRaw('
                    movements.account_id,
                    movements.currency,
                    COUNT(*) as total_movements,
                    COUNT(CASE WHEN movements.amount_neto > 0 THEN 1 END) as income_movements,
                    SUM(CASE 
                        WHEN movements.amount_neto > 0 
                        THEN movements.amount_neto * (courses.comission / 100) 
                        ELSE 0 
                    END) as total_ganancia
                ')
                ->whereNotNull('movements.account_id')
                ->whereNotNull('movements.course_id')
                ->whereNotNull('courses.comission')
                ->groupBy(['movements.account_id', 'movements.currency'])
                ->get();

            // Agrupar por cuenta y calcular ganancias
            $byAccountsGanancias = [];
            $accountGroupsGanancias = $accountGanancias->groupBy('account_id');

            foreach ($accountGroupsGanancias as $accountId => $ganancias) {
                $accountName = Cuentas::where('id', $accountId)->first()->nombre ?? 'Sin cuenta';
                
                $account_ganancia_ars_total = 0;
                $account_ganancia_usd_total = 0;
                $account_ganancia_ars_total_movements = 0;
                $account_ganancia_ars_income_movements = 0;
                $account_ganancia_usd_total_movements = 0;
                $account_ganancia_usd_income_movements = 0;

                foreach ($ganancias as $ganancia) {
                    if ($ganancia->currency == 2) { // ARS
                        $account_ganancia_ars_total = $ganancia->total_ganancia;
                        $account_ganancia_ars_total_movements = $ganancia->total_movements;
                        $account_ganancia_ars_income_movements = $ganancia->income_movements;
                    } elseif ($ganancia->currency == 1) { // USD
                        $account_ganancia_usd_total = $ganancia->total_ganancia;
                        $account_ganancia_usd_total_movements = $ganancia->total_movements;
                        $account_ganancia_usd_income_movements = $ganancia->income_movements;
                    }
                }

                $byAccountsGanancias[] = [
                    'account_id' => (int) $accountId,
                    'account_name' => $accountName,
                    'ganancia_ars_total' => (float) $account_ganancia_ars_total,
                    'ganancia_ars_total_movements' => (int) $account_ganancia_ars_total_movements,
                    'ganancia_ars_income_movements' => (int) $account_ganancia_ars_income_movements,
                    'ganancia_usd_total' => (float) $account_ganancia_usd_total,
                    'ganancia_usd_total_movements' => (int) $account_ganancia_usd_total_movements,
                    'ganancia_usd_income_movements' => (int) $account_ganancia_usd_income_movements
                ];
            }

            // ============ CALCULAR GASTOS ============
            // Calcular gastos totales por moneda usando amount_neto (movimientos negativos atados a cursos)
            $totalGastosQuery = clone $baseQuery;
            $totalGastos = $totalGastosQuery
                ->selectRaw('
                    currency,
                    COUNT(*) as total_movements,
                    COUNT(CASE WHEN amount_neto < 0 THEN 1 END) as outcome_movements,
                    SUM(CASE WHEN amount_neto < 0 THEN ABS(amount_neto) ELSE 0 END) as total_gasto
                ')
                ->groupBy('currency')
                ->get();

            // Inicializar gastos con valores por defecto
            $gasto_ars_total = 0;
            $gasto_ars_total_movements = 0;
            $gasto_ars_outcome_movements = 0;
            $gasto_usd_total = 0;
            $gasto_usd_total_movements = 0;
            $gasto_usd_outcome_movements = 0;

            // Asignar valores según moneda
            foreach ($totalGastos as $gasto) {
                if ($gasto->currency == 2) { // ARS
                    $gasto_ars_total = $gasto->total_gasto;
                    $gasto_ars_total_movements = $gasto->total_movements;
                    $gasto_ars_outcome_movements = $gasto->outcome_movements;
                } elseif ($gasto->currency == 1) { // USD
                    $gasto_usd_total = $gasto->total_gasto;
                    $gasto_usd_total_movements = $gasto->total_movements;
                    $gasto_usd_outcome_movements = $gasto->outcome_movements;
                }
            }

            // Calcular gastos por cuenta
            $accountGastosQuery = clone $baseQuery;
            $accountGastos = $accountGastosQuery
                ->selectRaw('
                    account_id,
                    currency,
                    COUNT(*) as total_movements,
                    COUNT(CASE WHEN amount_neto < 0 THEN 1 END) as outcome_movements,
                    SUM(CASE WHEN amount_neto < 0 THEN ABS(amount_neto) ELSE 0 END) as total_gasto
                ')
                ->whereNotNull('account_id')
                ->groupBy(['account_id', 'currency'])
                ->get();

            // Agrupar por cuenta y calcular gastos
            $byAccountsGastos = [];
            $accountGroupsGastos = $accountGastos->groupBy('account_id');

            foreach ($accountGroupsGastos as $accountId => $gastos) {
                $accountName = Cuentas::where('id', $accountId)->first()->nombre ?? 'Sin cuenta';
                
                $account_gasto_ars_total = 0;
                $account_gasto_usd_total = 0;
                $account_gasto_ars_total_movements = 0;
                $account_gasto_ars_outcome_movements = 0;
                $account_gasto_usd_total_movements = 0;
                $account_gasto_usd_outcome_movements = 0;

                foreach ($gastos as $gasto) {
                    if ($gasto->currency == 2) { // ARS
                        $account_gasto_ars_total = $gasto->total_gasto;
                        $account_gasto_ars_total_movements = $gasto->total_movements;
                        $account_gasto_ars_outcome_movements = $gasto->outcome_movements;
                    } elseif ($gasto->currency == 1) { // USD
                        $account_gasto_usd_total = $gasto->total_gasto;
                        $account_gasto_usd_total_movements = $gasto->total_movements;
                        $account_gasto_usd_outcome_movements = $gasto->outcome_movements;
                    }
                }

                $byAccountsGastos[] = [
                    'account_id' => (int) $accountId,
                    'account_name' => $accountName,
                    'gasto_ars_total' => (float) $account_gasto_ars_total,
                    'gasto_ars_total_movements' => (int) $account_gasto_ars_total_movements,
                    'gasto_ars_outcome_movements' => (int) $account_gasto_ars_outcome_movements,
                    'gasto_usd_total' => (float) $account_gasto_usd_total,
                    'gasto_usd_total_movements' => (int) $account_gasto_usd_total_movements,
                    'gasto_usd_outcome_movements' => (int) $account_gasto_usd_outcome_movements
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
                ],
                'ganancias' => [
                    'ganancia_ars_total' => (float) $ganancia_ars_total,
                    'ganancia_ars_total_movements' => (int) $ganancia_ars_total_movements,
                    'ganancia_ars_income_movements' => (int) $ganancia_ars_income_movements,
                    'ganancia_usd_total' => (float) $ganancia_usd_total,
                    'ganancia_usd_total_movements' => (int) $ganancia_usd_total_movements,
                    'ganancia_usd_income_movements' => (int) $ganancia_usd_income_movements,
                    'by_accounts' => $byAccountsGanancias
                ],
                'gastos' => [
                    'gasto_ars_total' => (float) $gasto_ars_total,
                    'gasto_ars_total_movements' => (int) $gasto_ars_total_movements,
                    'gasto_ars_outcome_movements' => (int) $gasto_ars_outcome_movements,
                    'gasto_usd_total' => (float) $gasto_usd_total,
                    'gasto_usd_total_movements' => (int) $gasto_usd_total_movements,
                    'gasto_usd_outcome_movements' => (int) $gasto_usd_outcome_movements,
                    'by_accounts' => $byAccountsGastos
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