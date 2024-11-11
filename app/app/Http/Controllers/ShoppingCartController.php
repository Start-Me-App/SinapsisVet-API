<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{ShoppingCart, User,ShoppingCartContent,Inscriptions};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class ShoppingCartController extends Controller
{   

    /**
     * Get active shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function get(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        return response()->json(['data' => $shoppingCart], 200);

    }


     /**
     * Add item to shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function addItem(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        #check if user is already inscribed
        $inscripcion = Inscriptions::where('user_id', $user->id)->where('course_id', $request->course_id)->first();

        if($inscripcion){
            return response()->json(['message' => 'Ya estás inscripto en este curso'], 400);
        }

        #check if item already exists
        $item = ShoppingCartContent::where('course_id', $request->course_id)->where('shopping_cart_id', $shoppingCart->id)->first();

        if($item){
           $item->with_workshop = $request->with_workshop;
            $item->save();
        }else{
            #add item to shopping cart
            $shoppingCartContent = new ShoppingCartContent();
            $shoppingCartContent->course_id = $request->course_id;
            $shoppingCartContent->with_workshop = $request->with_workshop;
            $shoppingCartContent->shopping_cart_id = $shoppingCart->id;
            $shoppingCartContent->save();
        }


        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        return response()->json(['data' => $shoppingCart], 200);

    }


     /**
     * Remove item to shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function removeItem(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }

        #remove item to shopping cart
        ShoppingCartContent::where('course_id', $request->course_id)->where('shopping_cart_id', $shoppingCart->id)->delete();

        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        return response()->json(['data' => $shoppingCart], 200);

    }


    /**
     * Processs a shopping cart
     *
     * @param $provider
     * @return JsonResponse
     */
    public function process(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user = TokenManager::getUserFromToken($accessToken);

        $shoppingCart = ShoppingCart::with(['items'])->where('user_id', $user->id)->where('active', 1)->first();

        if(!$shoppingCart){
            return response()->json(['message' => 'Carrito no encontrado'], 404);
        }
       
       foreach ($shoppingCart->items as $item) {

            #inscribir al curso
            $inscripcion = new Inscriptions();
            $inscripcion->user_id = $user->id;
            $inscripcion->course_id = $item->course_id;
            $inscripcion->with_workshop = $item->with_workshop;
            $inscripcion->save();
       }

        #desactivar carrito
        $shoppingCart->active = 0;
        $shoppingCart->save();

        #crear nuevo carrito

        $newShoppingCart = new ShoppingCart();
        $newShoppingCart->user_id = $user->id;
        $newShoppingCart->active = 1;
        $newShoppingCart->save();
        
        $shoppingCart = ShoppingCart::with(['items.course'])->where('user_id', $user->id)->where('active', 1)->first();

        return response()->json(['msg' => 'Carrito procesado correctamente'], 200);

    }



}