<?php

namespace App\Http\Controllers\API;


use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use App\Imports\FlottageImport;
use Illuminate\Http\Request;
use App\Approvisionnement;
use App\Destockage;
use App\Enums\Statut;
use App\Enums\Roles;
use App\Type_puce;
use App\Flote;
use App\Puce;
use App\Retour_flote;

class ImportFlottageController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $superviseur = Roles::SUPERVISEUR;
        $this->middleware("permission:$superviseur");

    }


    public function import_flotage(Request $request)
    {


        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'fichier' => ['required', 'file', 'max:10000'],
            'id_puce' => ['required', 'Numeric'],
            'entete' => ['required', 'Numeric']
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On recupère la puce dont on veut le listing
        $puce_etp = Puce::find($request->id_puce);

        //on recupère le type de la puce
        $type_puce = Type_puce::find($puce_etp->type)->name;

        //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage
        if ($type_puce != Statut::FLOTTAGE && $type_puce != Statut::FLOTTAGE_SECONDAIRE) {
            return response()->json(
                [
                    'message' => "cette puce n'est pas une puce de flottagage",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //les flotages
            $flotages = Approvisionnement::where('from', $request->id_puce);

            //nbre d'enregistrement
            $nbre_flotages = $flotages->count();

            //somme totale
            $solde_flotages = $flotages->sum('montant');


        //les destockages
            $destockages = Destockage::where('id_puce', $request->id_puce);

            //nbre d'enregistrement
            $nbre_destockages = $destockages->count();

            //somme totale
            $solde_destockages = $destockages->sum('montant');


        //les retours flote
            $retour_flotes = Retour_flote::where('user_destination', $request->id_puce);

            //nbre d'enregistrement
            $nbre_retour_flotes = $retour_flotes->count();

            //somme totale
            $solde_retour_flotes = $retour_flotes->sum('montant');


        //Nombre total de transaction
        $lignes_totales = $nbre_flotages + $nbre_destockages + $nbre_retour_flotes;


        //solde total des transactions
        $solde_total = $solde_retour_flotes + $solde_destockages - $solde_flotages;



        $fichier = null;
        if ($request->hasFile('fichier') && $request->file('fichier')->isValid()) {
            $fichier = $request->fichier->store('files/fichiers');
        }

        //collection importée
        $import_collection = Excel::toCollection(new FlottageImport($request->entete), $fichier)->first();

        //nbre d'enregistrement importée
        $nbre_import = $import_collection->count();

        //somme totale importée
        $solde_import = $import_collection->sum('montant');


        //rapport de comparaison

        if ($nbre_import == $lignes_totales && $solde_import == $solde_total) {
            $rapport = "La comparaison ne ressort aucune difference, tout est parfait";
            $severite = 'success';
        }elseif ($nbre_import == $lignes_totales && $solde_import != $solde_total) {
            $rapport = "Tous les enregistrement on bien été faits, mais il ya un problème au niveau des montants";
            $severite = 'warning';
        }elseif ($nbre_import != $lignes_totales && $solde_import == $solde_total) {
            $rapport = "Tout est bon au niveau des montanst mais, il ya innégalité au niveau des enregistrements";
            $severite = 'warning';
        }else {
            $rapport = "Rien ne marche, il ya innégalité tant sur le nombre d'enregistrements, que sur les montant";
            $severite = 'danger';
        }

        return response()->json(
            [
                'message' => "Comparaison effectuée",
                'status' => true,
                'data' => [
                    'rapport' => $rapport,
                    'severite' => $severite,
                    'solde_importe' => $solde_import,
                    'lignes_importes' => $nbre_import,
                    'solde_applications' => $solde_total,
                    "lignes_applications" => $lignes_totales,
                ]
            ]
        );
    }


}
