<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\Event;
use App\Models\Matchs;
use App\Models\UserEvent;
use App\Models\UserDecision;
use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class MatchController extends Controller
{
    
    /**
     * get matchs by user 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function get(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

      
        $matchs = Matchs::with(['user1','user2','event'])->where('user_1_id', $user->id)->orWhere('user_2_id',$user->id)->get();

        #if im user 1 i get the user 2 and viceversa
        foreach ($matchs as $match) {
            if($match->user_1_id == $user->id){
                $match->user = $match->user2;
            }else{
                $match->user = $match->user1;
            }
            unset($match->user1);
            unset($match->user2);
        }


        return response()->json(['response' => $matchs], 200);
    }

    /**
     * delete match
     *
     * @param $provider
     * @return JsonResponse
     */
    public function delete(Request $request,$matchId)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

      
        $match = Matchs::where('id',$matchId)->where('user_1_id', $user->id)->orWhere('user_2_id',$user->id)->get();

        if(!$match){
            return response()->json(['error' => 'Match not found'], 401);
        }

        #delete match
        $match->delete();
        

        return response()->json(['response' => $match], 200);
    }

  

}