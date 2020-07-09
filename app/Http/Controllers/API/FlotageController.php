<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Agent;
use App\Demande_flote;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Puce;
use App\Caisse;
use Illuminate\Support\Facades\Auth;

class FlotageController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */

    function __construct(){
        $this->middleware('permission:Recouvreur|Gestionnaire_flotte');
    }








}
