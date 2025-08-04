<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Movements;
use App\Models\Cuentas;
use App\Helper\TelegramNotification;
use Carbon\Carbon;

class EnviarGananciasDiarias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ganancias:enviar-reporte-diario';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un reporte de ganancias del mes actual por Telegram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Iniciando cálculo de ganancias del mes actual...');
            
            // Obtener el período actual (mes/año)
            $currentPeriod = Carbon::now()->format('m-Y');
            $monthName = Carbon::now()->locale('es')->monthName;
            $year = Carbon::now()->year;
            
            $this->info("Calculando ganancias para: $monthName $year (Período: $currentPeriod)");
        
            // Crear query base para el período actual
            $baseQuery = Movements::query()->byPeriod($currentPeriod);
            
            // ============ CALCULAR SUBTOTALES POR MONEDA ============
            // Calcular subtotales de ingresos por moneda (amount_neto)
            $subtotalesQuery = clone $baseQuery;
            $subtotales = $subtotalesQuery
                ->selectRaw('
                    currency,
                    COUNT(*) as total_movements,
                    COUNT(CASE WHEN amount_neto > 0 THEN 1 END) as income_movements,
                    SUM(CASE WHEN amount_neto > 0 THEN amount_neto ELSE 0 END) as subtotal_ingresos
                ')
                ->groupBy('currency')
                ->get();

        
       

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

            $comision_ars = $ganancia_ars_total * 0.20;
            $comision_usd = $ganancia_usd_total * 0.20;

      

            // Preparar el mensaje para Telegram
            $mensaje = $this->prepararMensajeTelegram(
                $monthName,
                $year,
                $comision_ars,
                $comision_usd,
                $ganancia_ars_total,
                $ganancia_ars_income_movements,
                $ganancia_usd_total,
                $ganancia_usd_income_movements,
                $byAccountsGanancias
            );

            // Enviar por Telegram
            $telegram = new TelegramNotification();
            $telegram->enviarReporteGanancias($mensaje);

            $this->info('✅ Reporte de ganancias enviado exitosamente por Telegram');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error al calcular o enviar las ganancias: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Prepara el mensaje formateado para Telegram
     */
    private function prepararMensajeTelegram($monthName, $year, $comision_ars, $comision_usd, $ganancia_ars, $movimientos_ars, $ganancia_usd, $movimientos_usd)
    {
        $mensaje = "📊 *REPORTE DE GANANCIAS DIARIO*\n";
        $mensaje .= "🗓️ *Período:* {$monthName} {$year}\n";
        $mensaje .= "⏰ *Fecha del reporte:* " . Carbon::now()->format('d/m/Y H:i') . "\n\n";



        // ============ GANANCIAS TOTALES ============
        $mensaje .= "\n💰 *GANANCIAS TOTALES:*\n";
        
        if ($ganancia_ars > 0) {
            $mensaje .= "🇦🇷 ARS: $" . number_format($ganancia_ars, 2, ',', '.') . " ({$movimientos_ars} movimientos)\n";
        }
        
        if ($ganancia_usd > 0) {
            $mensaje .= "🇺🇸 USD: $" . number_format($ganancia_usd, 2, ',', '.') . " ({$movimientos_usd} movimientos)\n";
        }

        if ($ganancia_ars == 0 && $ganancia_usd == 0) {
            $mensaje .= "ℹ️ No hay ganancias registradas para este período\n";
        }


        // ============ NUESTRA COMISIÓN (20%) ============
        $mensaje .= "\n🏦 *NUESTRA COMISIÓN (20%):*\n";
    
        if ($comision_ars > 0) {
            $mensaje .= "🇦🇷 ARS: $" . number_format($comision_ars, 2, ',', '.') . "\n";
        }
        
        if ($comision_usd > 0) {
            $mensaje .= "🇺🇸 USD: $" . number_format($comision_usd, 2, ',', '.') . "\n";
        }
     

        $mensaje .= "\n🤖 *Reporte automático generado por SinapsisVet*";

        return $mensaje;
    }
}