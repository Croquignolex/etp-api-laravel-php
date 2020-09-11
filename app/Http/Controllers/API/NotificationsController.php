<?php

namespace App\Http\Controllers\API;
use App\Puce;
use App\User;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Destockage;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Destockage as Notif_destockage;
use App\Http\Resources\Destockage as DestockageResource;

class NotificationsController extends Controller
{

    /**
     * les conditions de lecture des methodes

     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $agent = Roles::AGENT;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");
    }

    //
    /**
     * ///////Recupérer toutes mes notifications
     */
    public function all_notifications()
    {
        $user = Auth::user();
        $my_notifications = $user->notifications;
        return response()->json(
            [
                'message' => "",
                'status' => true,
                'data' => $my_notifications
            ]
        );
    }

    //
    /**
     * /////Recupérer mes notifications non lues
     */
    public function unread_notifications()
    {
        $user = Auth::user();
        $my_notifications = $user->unreadNotifications ;
        return response()->json(
            [
                'message' => "",
                'status' => true,
                'data' => $my_notifications
            ]
        );
    }


    //
    /**
     * ////marquer comme lue
     */
    public function read_notifications($id)
    {
        
        $user = Auth::user();
        $my_notifications = $user->unreadNotifications ;

        //on verifi si la notification existe
        if (!$my_notification = $my_notifications->find($id)) {
            return response()->json(
                [
                    'message' => "la notificaton n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        $my_notification->markAsRead();

        return response()->json(
            [
                'message' => "",
                'status' => true,
                'data' => $my_notification
            ]
        );
    }


    //
    /**
     * ////marquer comme lue
     */
    public function delette_notifications($id)
    {
        
        $user = Auth::user();
        $my_notifications = $user->notifications ;

        //on verifi si la notification existe
        if (!$my_notification = $my_notifications->find($id)) {
            return response()->json(
                [
                    'message' => "la notificaton n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        $my_notification->delete();

        return response()->json(
            [
                'message' => "",
                'status' => true,
                'data' => $my_notification
            ]
        );
    }






}
