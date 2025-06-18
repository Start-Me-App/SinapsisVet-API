<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Movements;
use Illuminate\Support\Facades\Validator;

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
                'course_id' => 'nullable|integer|exists:courses,id'
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
                'course_id' => 'nullable|integer|exists:courses,id'
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
     * Obtener estadísticas de movimientos
     *
     * @return JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $totalMovements = Movements::count();
            
            // Total por moneda separado
            $totalAmountByCurrency = Movements::selectRaw('currency, SUM(amount) as total_amount')
                ->groupBy('currency')
                ->get()
                ->mapWithKeys(function ($item) {
                    $currencyName = Movements::CURRENCIES[$item->currency] ?? 'Unknown';
                    return [$currencyName => $item->total_amount];
                });

            // Estadísticas por año separadas por moneda
            $yearStats = Movements::selectRaw('RIGHT(period, 4) as year, currency, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy(['year', 'currency'])
                ->orderBy('year', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => $item->year,
                        'currency_id' => $item->currency,
                        'currency_name' => Movements::CURRENCIES[$item->currency] ?? 'Unknown',
                        'count' => $item->count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Estadísticas por mes del año actual separadas por moneda
            $currentYear = date('Y');
            $monthStats = Movements::selectRaw('LEFT(period, 2) as month, currency, COUNT(*) as count, SUM(amount) as total_amount')
                ->where('period', 'like', '%/' . $currentYear)
                ->groupBy(['month', 'currency'])
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'currency_id' => $item->currency,
                        'currency_name' => Movements::CURRENCIES[$item->currency] ?? 'Unknown',
                        'count' => $item->count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Estadísticas por moneda (sin cambios)
            $currencyStats = Movements::selectRaw('currency, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('currency')
                ->get()
                ->map(function ($item) {
                    return [
                        'currency_id' => $item->currency,
                        'currency_name' => Movements::CURRENCIES[$item->currency] ?? 'Unknown',
                        'count' => $item->count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Estadísticas por período específico separadas por moneda
            $periodStats = Movements::selectRaw('period, currency, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy(['period', 'currency'])
                ->orderBy('period', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => $item->period,
                        'currency_id' => $item->currency,
                        'currency_name' => Movements::CURRENCIES[$item->currency] ?? 'Unknown',
                        'count' => $item->count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Estadísticas por curso separadas por moneda
            $courseStats = Movements::with('course')
                ->selectRaw('course_id, currency, COUNT(*) as count, SUM(amount) as total_amount')
                ->whereNotNull('course_id')
                ->groupBy(['course_id', 'currency'])
                ->get()
                ->map(function ($item) {
                    return [
                        'course_id' => $item->course_id,
                        'course_name' => $item->course ? $item->course->name : 'Curso no encontrado',
                        'currency_id' => $item->currency,
                        'currency_name' => Movements::CURRENCIES[$item->currency] ?? 'Unknown',
                        'count' => $item->count,
                        'total_amount' => $item->total_amount
                    ];
                });

            // Movimientos sin curso asignado
            $movementsWithoutCourse = Movements::whereNull('course_id')->count();

            return response()->json([
                'total_movements' => $totalMovements,
                'total_amount_by_currency' => $totalAmountByCurrency,
                'movements_without_course' => $movementsWithoutCourse,
                'by_year' => $yearStats,
                'by_month_current_year' => $monthStats,
                'by_currency' => $currencyStats,
                'by_period' => $periodStats,
                'by_course' => $courseStats
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las estadísticas'], 500);
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