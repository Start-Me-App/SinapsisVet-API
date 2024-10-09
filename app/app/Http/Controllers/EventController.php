<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\JsonResponse;
use App\Models\Event;
use App\Models\UserEvent;
use Illuminate\Support\Facades\DB;

use App\Support\TokenManager;

class EventController extends Controller
{
    #define constants
    #define constant for other = 3

    const SEX_MALE = 1;
    const SEX_FEMALE = 2;
    const HETERO = 1;
    const HOMO = 2;



    /**
     * Create event
     *
     * @param $provider
     * @return JsonResponse
     */
    public function create(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data =  $request->all();
        
        $eventCreated = Event::firstOrCreate(
            [
                'lat' => $data['lat'],
                'long' => $data['long'],
                'dateStart' => $data['dateStart'],
                'dateEnd' => $data['dateEnd']
            ]
        );
        if(!$eventCreated){
            return response()->json(['error' => 'Error creating event'], 401);
        }

        #create event assistance

        $eventAssistance = UserEvent::firstOrCreate(
            [
                'user_id' => $user->id,
                'event_id' => $eventCreated->id
            ]
        );

        $events = Event::find($eventCreated->id);


        return response()->json($events);
    }

    private function calculateAge($dob)
    {
        $dob = date('Y-m-d', strtotime($dob));
        $dobObject = new \DateTime($dob);
        $nowObject = new \DateTime();
        $diff = $dobObject->diff($nowObject);
        return $diff->y;
    }


     /**
     * get event data
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getEventsByUser(Request $request)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }   

        #set filters - 

        #from this get only lat, long, dateStart, dateEnd
        
        $events = DB::table('user_event')
            ->join('events', 'user_event.event_id', '=', 'events.id')
            ->where('user_event.user_id', $user->id)
            ->get(['events.id','events.lat', 'events.long', 'events.dateStart', 'events.dateEnd']);

        
        

        return response()->json($events);
    }


    /**
     * get event pool data
     *
     * @param $provider
     * @return JsonResponse
     */
     public function getEventsPool(Request $request, $eventId)
    {

        $accessToken = TokenManager::getTokenFromRequest();
        $user        = TokenManager::getUserFromToken($accessToken);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }   

        #check if event exists
        $event = Event::find($eventId);
        if(!$event){
            return response()->json(['error' => 'Event not found'], 400);
        }

        #check if user is on the event

        $assist = UserEvent::where('event_id', $eventId)
        ->where('user_id', $user->id);

        if(!$assist){
            return response()->json(['error' => 'User not in event'], 400);
        }

        $filter = $this->setGenreFilter($user->genre->id,$user->sexual_orientation->id);

        $events = UserEvent::with(['user'])
        ->join('events', 'user_event.event_id', '=', 'events.id')
        ->leftJoin('users as pool_user', 'user_event.user_id', '=', 'pool_user.id')
        ->leftJoin('user_decision', 'user_decision.user_match_id', '=', 'pool_user.id')
        ->where('user_event.event_id', $eventId)
        ->where('user_event.user_id', '!=', $user->id)
        ->where('pool_user.genre_id', $filter['genre'])
        ->where('pool_user.sexual_orientation_id', $filter['sexual_orientation'])
        ->where('user_decision.decision', null)
        ->get(['pool_user.name','pool_user.age','pool_user.description','pool_user.id']);
        

        return response()->json($events);
    } 

    private function setGenreFilter($genre,$sexual_orientation)
    {

        $genre_filter = null;
        $sexual_orientation_filter = null;
        switch($genre){
            case self::SEX_MALE:
                if($sexual_orientation == self::HETERO){
                    $genre_filter = self::SEX_FEMALE;
                    $sexual_orientation_filter = self::HETERO;
                }
                if($sexual_orientation == self::HOMO){
                    $genre_filter = self::SEX_MALE;                    
                    $sexual_orientation_filter = self::HOMO;
                }                
                break;
            case self::SEX_FEMALE:
                if($sexual_orientation == self::HETERO){
                    $genre_filter = self::SEX_MALE;                    
                    $sexual_orientation_filter = self::HETERO;
                }
                if($sexual_orientation == 2){
                    $genre_filter = self::SEX_FEMALE;                    
                    $sexual_orientation_filter = self::HOMO;
                }                
                break;
        }
        return ['genre' => $genre_filter, 'sexual_orientation' => $sexual_orientation_filter];
    }


    
     /**
     * get events by timezone
     *
     * @param $provider
     * @return JsonResponse
     */
    public function getEvents(Request $request)
    {
        #get query params

        $filters = $request->all();


        $query = DB::table('events');

        if(isset($filters['dateStart'])){
            $query->where('events.dateStart', $filters['dateStart']);
        }

        if(isset($filters['dateEnd'])){
            $query ->where('events.dateEnd', $filters['dateEnd']);
        }
        
        if(isset($filters['lat'])){
            $query ->where('events.lat', $filters['lat']);
        }
        if(isset($filters['long'])){
            $query ->where('events.long', $filters['long']);
        }
        
        $events = $query->get();

        return response()->json($events);
    }
}