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

class UserDecisionController extends Controller
{
    #define constants
    const YES = 1;
    const NO = 0;

    /**
     * make decision 
     *
     * @param $provider
     * @return JsonResponse
     */
    public function decide(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data =  $request->all();

        $event = Event::find($data['event_id']);
        if(!$event){
            return response()->json(['error' => 'Event not found'], 401);
        }

        
        #check if user is on the event
        $assist = UserEvent::where('event_id', $data['event_id'])
        ->where('user_id', $user->id);

        if($assist->count() == 0){
            return response()->json(['error' => 'User not on event'], 401);
        }

    
        #check if user match is on the event
        $assist = UserEvent::where('event_id', $data['event_id'])
        ->where('user_id', $data['user_match_id']);

        if($assist->count() == 0){
            return response()->json(['error' => 'Other user not on event'], 401);
        }

        $decision = UserDecision::firstOrCreate(
            [
                'user_owner_id' => $user->id,
                'user_match_id' => $data['user_match_id'],
                'event_id' => $data['event_id'],
                'decision' => $data['decision']
            ]
        );

        if(!$decision){
            return response()->json(['error' => 'Error creating decision'], 401);
        }


        #check if the other user has already made a decision
        $otherDecision = UserDecision::where('user_owner_id', $data['user_match_id'])
        ->where('user_match_id', $user->id)
        ->where('event_id', $data['event_id'])->first();
       
        $match = null;
        if(!is_null($otherDecision) &&  $otherDecision->decision == 1){
            $match = 1;
            #create match
             $match = Matchs::firstOrCreate(
                [
                    'user_1_id' => $user->id,
                    'user_2_id' => $data['user_match_id'],
                    'event_id' => $data['event_id'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ); 
        }


        return response()->json(['match' => $match,'decision' => $decision], 200);
    }

  

}