<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\{Categories, Countries};

use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class CategoriesController extends Controller
{   

  
    /**
     * Listado de paises
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getAll(Request $request)
    {   

        $params = $request->all();
      
        $list = Categories::all();
      
        return response()->json(['data' => $list], 200);
    }
}