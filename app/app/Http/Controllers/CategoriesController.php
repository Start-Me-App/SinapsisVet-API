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


    /**
     * create categories
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {   

        $params = $request->all();
      
        $create = new Categories();
        $create->name = $params['name'];
        $create->save();


      
        return response()->json(['data' => $create, 'msg' => 'Categoria creada correctamente'], 200);
    }


     /**
     * update categories
     *
     * @param $provider
     * @return JsonResponse
     */
    public function update(Request $request,$category_id)
    {   

        $params = $request->all();
      
        $category = Categories::find($category_id);

        if(!$category){
            return response()->json(['error' => 'Categoria no encontrada'], 400);
        }

        $category->name = $params['name'];
        $category->save();


      
        return response()->json(['data' => $category, 'msg' => 'Categoria modificada correctamente'], 200);
    }


     /**
     * delete categories
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$category_id)
    {   

        $params = $request->all();
      
        $category = Categories::find($category_id);

        if(!$category){
            return response()->json(['error' => 'Categoria no encontrada'], 400);
        }

       
        $category->delete();


      
        return response()->json(['data' => $category, 'msg' => 'Categoria eliminada correctamente'], 200);
    }


    
}