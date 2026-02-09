<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Order,OrderDetail,Inscriptions,Installments,InstallmentDetail,Discounts,Movements,Courses};

use App\Helper\TelegramNotification;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use App\Support\TokenManager;

class OrdersController extends Controller
{   

  
    /**
     * Listado de ordenes
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {   
        $params = $request->all();
      
     
        $list = Order::with('user','orderDetails')->orderBy('id', 'desc')->get();
      
        return response()->json(['data' => $list], 200);
    }

    public function getOrderDetails($order_id)
    {
        $order = OrderDetail::with('course.workshops','order')->where('order_id', $order_id)->get();
        return response()->json(['data' => $order], 200);
    }

    public function acceptOrder(Request $request,$order_id)
    {
        $installments = $request->input('installments');
        $account_id = $request->input('account_id');
        $commission_percentage = $request->input('commission_percentage', 0);
        $currency = $request->input('currency');
        if(!$installments && !$account_id){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago cuando no hay cuotas'], 500);
        }

        $order = Order::find($order_id);

        if($order->payment_method_id == 2 ){  
            if($order->status != 'pending'){
                return response()->json(['error' => 'La orden ya fue procesada'], 500);
            }
            try{
                $orderDetails = OrderDetail::where('order_id', $order_id)->get();
                foreach($orderDetails as $item){
                    $inscripcion = Inscriptions::where('user_id', $order->user_id)->where('course_id', $item->course_id)->first();
                    if(!$inscripcion){
                        $inscripcion = new Inscriptions();
                        $inscripcion->user_id = $order->user_id;
                        $inscripcion->course_id = $item->course_id;
                        $inscripcion->with_workshop = $item->with_workshop;
                        $inscripcion->save();
                    }else{
                        if($inscripcion->with_workshop == 0 && $item->with_workshop == 1){
                            $inscripcion->with_workshop = 1;
                            $inscripcion->save();
                        }
                    }
                }

                if($installments){
                    $installment = new Installments();
                    $installment->order_id = $order_id;
                    $installment->due_date = date('Y-m-d', strtotime('+' . $installments . ' months'));
                    $installment->status = 'pending';
                    $installment->amount = $installments;
                    $installment->date_created = date('Y-m-d H:i:s');
                    $installment->date_last_updated = date('Y-m-d H:i:s');
                    $installment->save();

                    for($i = 1; $i <= $installments; $i++){
                        $installmentDetail = new InstallmentDetail();
                        $installmentDetail->installment_id = $installment->id;
                        $installmentDetail->installment_number = $i;
                        $installmentDetail->due_date = date('Y-m-d', strtotime('+' . $i-1 . ' months'));
                        $installmentDetail->save();
                    }

                    $order->installments = $installments;
                    $order->save(); 
                } else {
                    // Crear movimientos cuando no hay cuotas
                    // Calcular el total original para aplicar descuentos proporcionalmente
                    $totalOriginal = $orderDetails->sum('price');
                    $totalConDescuento = $totalOriginal;
                    
                    // Aplicar descuentos porcentuales
                    if($order->discount_percentage > 0){
                        $totalConDescuento = $totalConDescuento - ($totalConDescuento * $order->discount_percentage / 100);
                    }
                    if($order->discount_percentage_coupon > 0){
                        $totalConDescuento = $totalConDescuento - ($totalConDescuento * $order->discount_percentage_coupon / 100);
                    }
                    
                    // Aplicar descuentos por monto fijo según la moneda
                    if($currency == 2 && $order->discount_amount_ars > 0){ // ARS
                        $totalConDescuento = $totalConDescuento - $order->discount_amount_ars;
                    }
                    if($currency == 1 && $order->discount_amount_usd > 0){ // USD
                        $totalConDescuento = $totalConDescuento - $order->discount_amount_usd;
                    }
                    
                    // Asegurar que el total no sea negativo
                    $totalConDescuento = max(0, $totalConDescuento);
                    
                    // Calcular factor de descuento para aplicar proporcionalmente a cada item
                    $factorDescuento = $totalOriginal > 0 ? $totalConDescuento / $totalOriginal : 0;
                    
                    foreach($orderDetails as $item){
                        $course = Courses::find($item->course_id);
                        $movement = new Movements();
                        
                        // Aplicar descuento proporcionalmente al precio del item
                        $precioConDescuento = $item->price * $factorDescuento;
                        
                        $movement->amount = $precioConDescuento;
                        $movement->amount_neto = $precioConDescuento - ($precioConDescuento * $commission_percentage / 100);
                        $movement->currency = $currency; // ARS para transferencia
                        $movement->description = 'Pago por transferencia - Orden #'.$order->id.' - Curso: '.$course->title;
                        $movement->course_id = $item->course_id;
                        $movement->period = date('m-Y');
                        $movement->account_id = $account_id;
                        $movement->save();
                    }
                }

            }catch(\Exception $e){
                return response()->json(['error' => $e->getMessage()], 500);
            }
            $order->status = 'paid';
            $order->date_paid = date('Y-m-d H:i:s');
            $order->date_closed = date('Y-m-d H:i:s');
            $order->save();
        }else{
            return response()->json(['error' => 'El pago no se pudo procesar, solo es posible con transferencia'], 500);
        }
        return response()->json(['order' => $order], 200);
    }


    public function rejectOrder($order_id)  
    {
        $order = Order::find($order_id);

        if($order->payment_method_id == 2){
            if($order->status != 'pending'){
                return response()->json(['error' => 'La orden ya fue procesada'], 500);
            }
        }else{
            return response()->json(['error' => 'El pago no se pudo procesar, solo es posible con transferencia'], 500);
        }

        $order->status = 'rejected';
        $order->date_closed = date('Y-m-d H:i:s');
        $order->save();
        return response()->json(['data' => $order], 200);
    }

      /**
     * Listado de ordenes del usuario
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getMyOrders(Request $request)
    {   
        $params = $request->all();

        
        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);


      
        $list = Order::with('orderDetails')->where('user_id', $user->id)->get();

        foreach($list as $item){          
            $total =  $item->orderDetails->sum('price');
            $item->setAttribute('total', $total);
    
        }


        return response()->json(['data' => $list], 200);
    }




    public function cleanUpOrders()
    {   
        #get all orders with status pending older than 2 weeks
        $orders = Order::where('status', 'pending')->where('date_created', '<', now()->subWeek(2))->get();

        foreach($orders as $order){
            $order->status = 'annulled';
            $order->date_closed = date('Y-m-d H:i:s');
            $order->save();
        }
        return response()->json(['message' => 'Orders cleaned up'], 200);
    }


    public function getInstallments($order_id)
    {
        $installments = Installments::with('installmentDetails.installmentMovement','order.orderDetails.course')->where('order_id', $order_id)->get();
        return response()->json(['data' => $installments], 200);
    }


    public function updateInstallmentDetail($installment_id,Request $request)
    {

        $account_id = $request->input('account_id');
        if(!$account_id){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago'], 500);
        }

        if($account_id == 2 || $account_id == 7){
            $currency = 1;
        }else{
            $currency = 2;
        }

        $commission_percentage = $request->input('commission_percentage');

        $installmentDetail = InstallmentDetail::find($installment_id);

        $installmentDetail->url_payment = $request->input('url_payment');

        // Actualizar fecha de vencimiento si se proporciona
        if($request->has('due_date') && $request->input('due_date')){
            $installmentDetail->due_date = $request->input('due_date');
        }
        if($request->input('paid')){
            $installmentDetail->paid_date = date('Y-m-d H:i:s');


            #find installment 
            $installment = Installments::find($installmentDetail->installment_id);
            $order = Order::find($installment->order_id);

            #find order details
            $orderDetails = OrderDetail::where('order_id', $order->id)->get();
            
            // Aplicar descuentos como en acceptOrder
            $totalOriginal = $orderDetails->sum('price');
            $totalConDescuento = $totalOriginal;
            
            // Aplicar descuentos porcentuales
            if($order->discount_percentage > 0){
                $totalConDescuento = $totalConDescuento - ($totalConDescuento * $order->discount_percentage / 100);
            }
            if($order->discount_percentage_coupon > 0){
                $totalConDescuento = $totalConDescuento - ($totalConDescuento * $order->discount_percentage_coupon / 100);
            }
            
            // Aplicar descuentos por monto fijo según la moneda (cuotas siempre en ARS)
            if($order->discount_amount_ars > 0){
                $totalConDescuento = $totalConDescuento - $order->discount_amount_ars;
            }
            
            // Asegurar que el total no sea negativo
            $totalConDescuento = max(0, $totalConDescuento);
            
            // Calcular factor de descuento para aplicar proporcionalmente a cada item
            $factorDescuento = $totalOriginal > 0 ? $totalConDescuento / $totalOriginal : 0;
            
            foreach($orderDetails as $item){
                $course = Courses::find($item->course_id);
                if($installmentDetail->paid == 0){
                    // Aplicar descuento proporcionalmente al precio del item
                    $precioConDescuento = $item->price * $factorDescuento;
                    $montoCuota = $precioConDescuento / $installment->amount;
                    
                    $movement = new Movements();
                    $movement->amount = $montoCuota;
                    $movement->amount_neto = $montoCuota - ($montoCuota * $commission_percentage / 100);
                    $movement->currency = $currency;
                    $movement->description = 'Orden #'.$order->id.' - Pago de cuota #'.$installmentDetail->id.' - Curso: '.$course->title;
                    $movement->course_id = $item->course_id;
                    $movement->period = date('m-Y');
                    $movement->account_id = $request->input('account_id');
                    $movement->save();
                    $installmentDetail->movement_id = $movement->id;
                    $installmentDetail->save();
                }
            }
        }else{
            $installmentDetail->paid_date = null;
            $installmentDetail->movement_id = null;
            $installmentDetail->save();
        }
        $installmentDetail->paid = $request->input('paid');
        $installmentDetail->save();


        $header_installment = Installments::find($installmentDetail->installment_id);
        $header_installment->date_last_updated = date('Y-m-d H:i:s');
        $header_installment->save();


        $installment_aux = Installments::with('installmentDetails')->find($installmentDetail->installment_id);
      
        $all_paid = true;
        foreach($installment_aux->installmentDetails as $item){
            if(!$item->paid){
                $all_paid = false;
            }
        }
        if($all_paid){
            $installment = Installments::find($installmentDetail->installment_id);
            $installment->status = 'paid';
            $installment->save();
        }
        return response()->json(['data' => $installmentDetail], 200);
    }





    /**
     * Actualizar solo la fecha de vencimiento de una cuota
     *
     * @param int $installment_id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateInstallmentDueDate($installment_id, Request $request): JsonResponse
    {
        try {
            $validator = validator($request->all(), [
                'due_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $installmentDetail = InstallmentDetail::find($installment_id);

            if (!$installmentDetail) {
                return response()->json(['error' => 'Cuota no encontrada'], 404);
            }

            // Guardar fecha anterior para el log
            $oldDueDate = $installmentDetail->due_date;

            // Actualizar fecha de vencimiento
            $installmentDetail->due_date = $request->input('due_date');
            $installmentDetail->save();

            // Actualizar fecha de última actualización del installment padre
            $installment = Installments::find($installmentDetail->installment_id);
            if ($installment) {
                $installment->date_last_updated = date('Y-m-d H:i:s');
                $installment->save();
            }

            return response()->json([
                'message' => 'Fecha de vencimiento actualizada correctamente',
                'data' => [
                    'installment_detail_id' => $installmentDetail->id,
                    'installment_number' => $installmentDetail->installment_number,
                    'old_due_date' => $oldDueDate,
                    'new_due_date' => $installmentDetail->due_date,
                    'paid' => $installmentDetail->paid,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la fecha de vencimiento',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllInstallments(Request $request)
    {
        $filters['status'] = $request->input('status');

        if($filters['status']){
            $installments = Installments::with('order.user','installmentDetails')->where('status', $filters['status'])->get();
        }else{
            $installments = Installments::with('order.user','installmentDetails')->get();
        }

        foreach($installments as $item){
            $item->setAttribute('next_due_date', null);
            foreach($item->installmentDetails as $detail){
                if(!$detail->paid){
                   $next_due_date = $detail->due_date;
                   $next_due_url_payment = $detail->url_payment;
                   $item->setAttribute('next_due_date', $next_due_date);
                   $item->setAttribute('next_due_url_payment', $next_due_url_payment);
                   break;
                }
            }
        }
        return response()->json(['data' => $installments], 200);
    }


    public function createOrder(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $request_data = $request->all();

        if(!isset($request_data['account_id']) && $request_data['installments'] == 0){
            return response()->json(['error' => 'Se necesita una cuenta para registrar el pago'], 500);
        }

        $commission_percentage = isset($request_data['commission_percentage']) ? $request_data['commission_percentage'] : 0;
        
        $discount_percentage = isset($request_data['discount_percentage']) ? $request_data['discount_percentage'] : 0;
        $currency = null;


        $order = new Order();
        $order->user_id = $request->input('user_id');
        $order->status = 'paid';
        $order->date_created = date('Y-m-d H:i:s');
        $order->date_last_updated = date('Y-m-d H:i:s');
        $order->date_closed = date('Y-m-d H:i:s');
        $order->date_paid = date('Y-m-d H:i:s');
        $order->payment_method_id = $request_data['payment_method_id'];
        $order->shopping_cart_id = null;
        $order->save();

        $total = 0;

       
        foreach($request_data['items'] as $item){
            $orderDetails = new OrderDetail();
            $orderDetails->order_id = $order->id;
            $orderDetails->course_id = $item['course_id'];
            $orderDetails->price = $item['unit_price'];
            $orderDetails->with_workshop = $item['with_workshop'];
            if($item['with_workshop'] == 1){
                if($request_data['payment_method_id'] == 1 || $request_data['payment_method_id'] == 2){ # 1 mp y 2 transf
                    $orderDetails->price = $item['unit_price'] + env('WORKSHOP_PRICE_ARS');
                }else{ # 3 stripe y 4 paypal
                    $orderDetails->price = $item['unit_price'] + env('WORKSHOP_PRICE_USD');
                }
            }
            $orderDetails->quantity = 1;
            $orderDetails->save();

            $total += $orderDetails->price;
        }
        if($discount_percentage > 0){
            $order->discount_amount_ars = $total * $discount_percentage / 100;
            $order->discount_amount_usd = $total * $discount_percentage / 100;
            $total = $total - ($total * $discount_percentage / 100);
            $order->discount_percentage = $discount_percentage;
            $order->save();
        }

        if($request_data['payment_method_id'] == 1 || $request_data['payment_method_id'] == 2){ # 1 mp y 2 transf
            $order->total_amount_usd = null;
            $order->total_amount_ars = $total;         
            $order->discount_amount_usd = null;
            $currency = 2;

        }else{ # 3 stripe y 4 paypal
            $order->total_amount_usd = $total;
            $order->total_amount_ars = null;
            $order->discount_amount_ars = null;
            $currency = 1;
        }

        if($request_data['installments'] > 0){
            $installment = new Installments();
            $installment->order_id = $order->id;
            if(is_null($request_data['installments_date'])){
                $start_date =date('Y-m-d');
            }else{
                $start_date = $request_data['installments_date'];
            }
            $installment->due_date = date('Y-m-d', strtotime($start_date . ' +' . $request_data['installments'] . ' months'));
            $installment->status = 'pending';
            $installment->amount = $request_data['installments'];
            $installment->date_created = date('Y-m-d H:i:s');
            $installment->date_last_updated = date('Y-m-d H:i:s');
            $installment->save();

            for($i = 1; $i <= $request_data['installments']; $i++){
                $installmentDetail = new InstallmentDetail();
                $installmentDetail->installment_id = $installment->id;
                $installmentDetail->installment_number = $i;
                $installmentDetail->due_date = date('Y-m-d', strtotime($start_date . ' +' . $i-1 . ' months'));
                if($i <= $request_data['installments_paid']){
                    $installmentDetail->paid = 1;
                    $installmentDetail->paid_date = date('Y-m-d H:i:s');
                }
                $installmentDetail->save();
            }

            $order->installments = $request_data['installments'];
            $order->save(); 
        }

        $order->save();

        
        if($request_data['inscription']){
            foreach($request_data['items'] as $item){
                $inscriptions = new Inscriptions();
                $inscriptions->user_id = $request_data['user_id'];
                $inscriptions->course_id = $item['course_id'];
                $inscriptions->with_workshop = $item['with_workshop'];
                $inscriptions->save();
            }
        }
     


        if($request_data['installments'] == 0){
            $account_id = $request->input('account_id');
           
            foreach($request_data['items'] as $item){
                $movement = new Movements();
                $movement->amount = $item['unit_price'];    
                $movement->amount_neto = $item['unit_price'] - ($item['unit_price'] * $commission_percentage / 100);
                $movement->currency = $currency;
                #find course name
                $course = Courses::find($item['course_id']);
                $movement->description = 'Pago de orden #'.$order->id.' - Curso: '.$course->title;
                $movement->course_id = $item['course_id'];
                $movement->period = date('m-Y');
                $movement->created_at = date('Y-m-d H:i:s');
                $movement->account_id = $account_id;
                $movement->save();
            }
        }


        return response()->json(['data' => $order], 200);
    }


    public function getDiscountsForUser(Request $request,$user_id)
    {
        try{

        #count inscriptions of user
        $inscriptions = Inscriptions::where('user_id', $user_id)->count();

        #count courses of user
        $discounts = Discounts::where('courses_amount', '<=', $inscriptions)->orderBy('courses_amount', 'desc')->first();

        if(!$discounts){
            return  null;
        }

        return $discounts;

        }catch(\Exception $e){
            $telegram = new TelegramNotification();
            $telegram->toTelegram($e->getMessage());
            return null;
        }
    }

    /**
     * Exportar órdenes a Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToExcel(Request $request)
    {
        try {
            $query = Order::with(['user', 'orderDetails.course']);

            // Aplicar filtros de fecha si se proporcionan
            if ($request->has('date_from') && $request->date_from) {
                $query->where('date_created', '>=', $request->date_from . ' 00:00:00');
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('date_created', '<=', $request->date_to . ' 23:59:59');
            }

            $orders = $query->orderBy('id', 'desc')->get();

            // Crear clase para la exportación
            $export = new class($orders) implements FromCollection, WithHeadings, WithStyles, WithColumnWidths {
                private $orders;

                public function __construct($orders)
                {
                    $this->orders = $orders;
                }

                public function collection()
                {
                    $data = collect();

                    foreach ($this->orders as $order) {
                        // Fila de cabecera de la orden
                        $data->push([
                            $order->id,
                            $order->user->email ?? 'N/A',
                            $order->user->name ?? 'N/A',
                            $order->user->phone ?? 'N/A',
                            $this->getStatusInSpanish($order->status),
                            $this->getTotalAmount($order),
                            $this->getCurrency($order),
                            $this->getPaymentMethodName($order->payment_method_id),
                            $order->date_created ? $order->date_created->format('Y-m-d H:i:s') : 'N/A',
                            '', // Nombre curso (vacío para cabecera)
                            '', // Precio (vacío para cabecera)
                            ''  // Con taller (vacío para cabecera)
                        ]);

                        // Filas de detalles
                        foreach ($order->orderDetails as $detail) {
                            $data->push([
                                '', // ID orden (vacío para detalle)
                                '', // Email (vacío para detalle)
                                '', // Nombre (vacío para detalle)
                                '', // Teléfono (vacío para detalle)
                                '', // Estado (vacío para detalle)
                                '', // Total (vacío para detalle)
                                '', // Moneda (vacío para detalle)
                                '', // Método pago (vacío para detalle)
                                '', // Fecha (vacía para detalle)
                                $detail->course->title ?? 'N/A',
                                $detail->price,
                                $detail->with_workshop ? 'Sí' : 'No'
                            ]);
                        }
                    }

                    return $data;
                }

                public function headings(): array
                {
                    return [
                        'ID Orden',
                        'Email Usuario',
                        'Nombre Usuario',
                        'Teléfono Usuario',
                        'Estado',
                        'Total',
                        'Moneda',
                        'Método de Pago',
                        'Fecha Creación',
                        'Nombre Curso',
                        'Precio',
                        'Con Taller'
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $row = 2; // Empezar desde la fila 2 (después del encabezado)
                    
                    foreach ($this->orders as $order) {
                        // Estilo para la fila de cabecera
                        $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
                        $sheet->getStyle("A{$row}:L{$row}")->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE6F3FF');
                        
                        $row++;
                        
                        // Estilo para las filas de detalle
                        for ($i = 0; $i < $order->orderDetails->count(); $i++) {
                            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setItalic(true);
                            $sheet->getStyle("A{$row}:I{$row}")->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF0F0F0');
                            $row++;
                        }
                    }

                    return [];
                }

                public function columnWidths(): array
                {
                    return [
                        'A' => 10,  // ID Orden
                        'B' => 25,  // Email Usuario
                        'C' => 20,  // Nombre Usuario
                        'D' => 15,  // Teléfono Usuario
                        'E' => 15,  // Estado
                        'F' => 15,  // Total
                        'G' => 10,  // Moneda
                        'H' => 20,  // Método de Pago
                        'I' => 20,  // Fecha Creación
                        'J' => 30,  // Nombre Curso
                        'K' => 15,  // Precio
                        'L' => 12,  // Con Taller
                    ];
                }

                private function getPaymentMethodName($paymentMethodId)
                {
                    $methods = [
                        1 => 'MercadoPago',
                        2 => 'Transferencia',
                        3 => 'Stripe',
                        4 => 'Efectivo'
                    ];

                    return $methods[$paymentMethodId] ?? 'Desconocido';
                }

                private function getStatusInSpanish($status)
                {
                    $statuses = [
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'annulled' => 'Anulado',
                        'rejected' => 'Rechazado'
                    ];

                    return $statuses[$status] ?? $status;
                }

                private function getTotalAmount($order)
                {
                    if ($order->total_amount_usd && $order->total_amount_usd > 0) {
                        return number_format($order->total_amount_usd, 2);
                    } elseif ($order->total_amount_ars && $order->total_amount_ars > 0) {
                        return number_format($order->total_amount_ars, 2);
                    }
                    
                    return '0.00';
                }

                private function getCurrency($order)
                {
                    if ($order->total_amount_usd && $order->total_amount_usd > 0) {
                        return 'USD';
                    } elseif ($order->total_amount_ars && $order->total_amount_ars > 0) {
                        return 'ARS';
                    }
                    
                    return 'N/A';
                }
            };

            $filename = 'ordenes_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al exportar las órdenes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exportar cuotas (installments) a Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportInstallmentsToExcel(Request $request)
    {
        try {
            $query = Installments::with(['order.user', 'installmentDetails']);

            // Aplicar filtros de fecha si se proporcionan
            if ($request->has('date_from') && $request->date_from) {
                $query->where('date_created', '>=', $request->date_from . ' 00:00:00');
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('date_created', '<=', $request->date_to . ' 23:59:59');
            }

            // Aplicar filtro de estado si se proporciona
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $installments = $query->orderBy('id', 'desc')->get();

            // Crear clase para la exportación
            $export = new class($installments) implements FromCollection, WithHeadings, WithStyles, WithColumnWidths {
                private $installments;

                public function __construct($installments)
                {
                    $this->installments = $installments;
                }

                public function collection()
                {
                    $data = collect();

                    foreach ($this->installments as $installment) {
                        // Fila de cabecera del installment
                        $data->push([
                            $installment->id,
                            $installment->order_id,
                            $installment->order->user->email ?? 'N/A',
                            $installment->order->user->name ?? 'N/A',
                            $installment->order->user->telephone ?? 'N/A',
                            $this->getStatusInSpanish($installment->status),
                            $installment->amount,
                            $installment->due_date ? $installment->due_date : 'N/A',
                            $this->getTotalAmount($installment->order),
                            $this->getCurrency($installment->order),
                            $this->getInstallmentAmount($installment),
                            '', // Número de cuota (vacío para cabecera)
                            '', // Fecha vencimiento cuota (vacío para cabecera)
                            '', // Fecha pagado (vacío para cabecera)
                            '', // Pagado (vacío para cabecera)
                            ''  // URL de pago (vacío para cabecera)
                        ]);

                        // Filas de detalles de cuotas
                        foreach ($installment->installmentDetails as $detail) {
                            $data->push([
                                '', // ID installment (vacío para detalle)
                                '', // ID orden (vacío para detalle)
                                '', // Email (vacío para detalle)
                                '', // Nombre (vacío para detalle)
                                '', // Teléfono (vacío para detalle)
                                '', // Estado (vacío para detalle)
                                '', // Cantidad de cuotas (vacío para detalle)
                                '', // Fecha vencimiento (vacía para detalle)
                                '', // Total (vacío para detalle)
                                '', // Moneda (vacío para detalle)
                                '', // Monto cuota (vacío para detalle)
                                $detail->installment_number,
                                $detail->due_date ? $detail->due_date : 'N/A',
                                $detail->paid_date ? $detail->paid_date : 'N/A',
                                $detail->paid ? 'Sí' : 'No',
                                $detail->url_payment ? $detail->url_payment : 'N/A'
                            ]);
                        }
                    }

                    return $data;
                }

                public function headings(): array
                {
                    return [
                        'ID Installment',
                        'ID Orden',
                        'Email Usuario',
                        'Nombre Usuario',
                        'Teléfono Usuario',
                        'Estado Installment',
                        'Cantidad de Cuotas',
                        'Fecha Vencimiento',
                        'Total',
                        'Moneda',
                        'Monto Cuota',
                        'Número de Cuota',
                        'Fecha Vencimiento Cuota',
                        'Fecha Pagado',
                        'Pagado',
                        'URL de Pago'
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    $row = 2; // Empezar desde la fila 2 (después del encabezado)
                    
                    foreach ($this->installments as $installment) {
                        // Estilo para la fila de cabecera
                        $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
                        $sheet->getStyle("A{$row}:K{$row}")->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE6F3FF');
                        
                        $row++;
                        
                        // Estilo para las filas de detalle
                        for ($i = 0; $i < $installment->installmentDetails->count(); $i++) {
                            $sheet->getStyle("A{$row}:K{$row}")->getFont()->setItalic(true);
                            $sheet->getStyle("A{$row}:K{$row}")->getFill()
                                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFF0F0F0');
                            $row++;
                        }
                    }

                    return [];
                }

                public function columnWidths(): array
                {
                    return [
                        'A' => 15,  // ID Installment
                        'B' => 10,  // ID Orden
                        'C' => 25,  // Email Usuario
                        'D' => 20,  // Nombre Usuario
                        'E' => 15,  // Teléfono Usuario
                        'F' => 18,  // Estado Installment
                        'G' => 18,  // Cantidad de Cuotas
                        'H' => 18,  // Fecha Vencimiento
                        'I' => 15,  // Total
                        'J' => 10,  // Moneda
                        'K' => 15,  // Monto Cuota
                        'L' => 15,  // Número de Cuota
                        'M' => 20,  // Fecha Vencimiento Cuota
                        'N' => 18,  // Fecha Pagado
                        'O' => 10,  // Pagado
                        'P' => 30,  // URL de Pago
                    ];
                }

                private function getStatusInSpanish($status)
                {
                    $statuses = [
                        'pending' => 'Pendiente',
                        'paid' => 'Pagado',
                        'overdue' => 'Vencido',
                        'cancelled' => 'Cancelado'
                    ];

                    return $statuses[$status] ?? $status;
                }

                private function getTotalAmount($order)
                {
                    if ($order->total_amount_usd && $order->total_amount_usd > 0) {
                        return number_format($order->total_amount_usd, 2);
                    } elseif ($order->total_amount_ars && $order->total_amount_ars > 0) {
                        return number_format($order->total_amount_ars, 2);
                    }
                    
                    return '0.00';
                }

                private function getCurrency($order)
                {
                    if ($order->total_amount_usd && $order->total_amount_usd > 0) {
                        return 'USD';
                    } elseif ($order->total_amount_ars && $order->total_amount_ars > 0) {
                        return 'ARS';
                    }
                    
                    return 'N/A';
                }

                private function getInstallmentAmount($installment)
                {
                    $total = 0;
                    
                    if ($installment->order->total_amount_usd && $installment->order->total_amount_usd > 0) {
                        $total = $installment->order->total_amount_usd;
                    } elseif ($installment->order->total_amount_ars && $installment->order->total_amount_ars > 0) {
                        $total = $installment->order->total_amount_ars;
                    }
                    
                    if ($total > 0 && $installment->amount > 0) {
                        return number_format($total / $installment->amount, 2);
                    }
                    
                    return '0.00';
                }
            };

            $filename = 'cuotas_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al exportar las cuotas: ' . $e->getMessage()], 500);
        }
    }
}