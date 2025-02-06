<?php

declare(strict_types=1);
namespace App\Http\Controllers\Stripe;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Charge;
use App\Http\Controllers\Controller;
class Charges extends Controller
{
    public function charge(Request $request)
    {
        // Configura la clave secreta de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Crea un cargo en Stripe
            $charge = Charge::create([
                'amount' => $request->amount * 100, // El monto debe estar en centavos
                'currency' => 'usd',
                'description' => 'Pago de ejemplo',
                'source' => 'tok_visa'
            ]);

            // Si el pago es exitoso, puedes guardar la información en tu base de datos
            // y redirigir al usuario a una página de éxito.

            return response()->json(['success' => true, 'charge' => $charge]);

        } catch (\Exception $e) {
            // Si hay un error, devuelve un mensaje de error
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}