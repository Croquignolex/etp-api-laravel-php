<?php

namespace App\Http\Controllers\API;

use App\Approvisionnement;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Flote;
use App\Puce;
use App\Enums\Roles;
use Illuminate\Support\Facades\Validator;

use App\Imports\FlottageImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ImportFlottageController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $superviseur = Roles::SUPERVISEUR;
        $this->middleware("permission:$superviseur");

    } 


    public function import(Request $request) 
    {


        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'fichier' => ['required', 'file', 'max:10000'],
            'entete' => ['required', 'Numeric']
        ]);  

        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
        }
        
        $fichier = null;
        if ($request->hasFile('fichier') && $request->file('fichier')->isValid()) {
            $fichier = $request->fichier->store('files/fichiers');
        }

        $import_collection = Excel::toCollection(new FlottageImport($request->entete), $fichier);

        // $users = DB::table('users')
        //     ->join('contacts', 'users.id', '=', 'contacts.user_id')
        //     ->join('orders', 'users.id', '=', 'orders.user_id')
        //     ->select('users.*', 'contacts.phone', 'orders.price')
        //     ->get();

        return response()->json(
            [
                'fichier' => $import_collection->first()
            ]
        );
    }





}
