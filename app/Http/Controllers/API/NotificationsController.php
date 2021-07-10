<?php

namespace App\Http\Controllers\API;

use App\Approvisionnement;
use App\Enums\Roles;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");
    }

    /**
     * ///////Recupérer toutes mes notifications
     */
    public function all_notifications()
    {
        return response()->json([
            'message' => "",
            'status' => true,
            'data' => Auth::user()->notifications->sortByDesc('created_at')
        ]);
    }

    /**
     * /////Recupérer mes notifications non lues
     */
    public function unread_notifications()
    {
        return response()->json([
            'message' => "",
            'status' => true,
            'data' => Auth::user()->unreadNotifications->sortByDesc('created_at')
        ]);
    }

    /**
     * ////marquer comme lue
     */
    public function read_notifications($id)
    {
        $user = Auth::user();
        $my_notifications = $user->unreadNotifications ;

        //on verifi si la notification existe
        if (!$my_notification = $my_notifications->find($id)) {
            return response()->json([
                'message' => "Cette notificaton n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $my_notification->markAsRead();

        return response()->json([
            'message' => "",
            'data' =>  null,
            'status' => true
        ]);
    }

    /**
     * ////marquer comme lue
     */
    public function delete_notifications($id)
    {
        $user = Auth::user();
        $my_notifications = $user->notifications ;

        //on verifi si la notification existe
        if (!$my_notification = $my_notifications->find($id)) {
            return response()->json([
                'message' => "Cette notificaton n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $my_notification->delete();

        return response()->json([
            'message' => "Notification supprimée avec succès",
            'data' => null,
            'status' => true,
        ]);
    }

    /**
     * Factory reset
     */
    // ADMIN
    public function factory_reset()
    {
        DB::table('movements')->delete();
        DB::table('liquidites')->delete();
        DB::table('versements')->delete();
        DB::table('treasuries')->delete();
        DB::table('destockages')->delete();
        DB::table('flottage_rz')->delete();
        DB::table('transactions')->delete();
        DB::table('notifications')->delete();
        DB::table('recouvrements')->delete();
        DB::table('retour_flotes')->delete();
        DB::table('demande_flotes')->delete();
        DB::table('flotage_anonymes')->delete();
        DB::table('flottage_internes')->delete();
        DB::table('approvisionnements')->delete();
        DB::table('demande_destockages')->delete();

        DB::table('puces')->update(["solde" => 0]);
        DB::table('users')->update(["dette" => 0]);
        DB::table('caisses')->update(["solde" => 0]);
        DB::table('vendors')->update(["solde" => 0]);

        return response()->json([
            'message' => "Rémise à zéro du système éffectué avec succès",
            'data' => null,
            'status' => true,
        ]);
    }
}
