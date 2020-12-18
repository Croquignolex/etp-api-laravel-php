<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//si l'utilisateur n'est pas connecté
Route::get('not_login', 'API\LoginController@not_login')->name('not_login');

//l'utilisateur se connecte
Route::post('login', 'API\LoginController@login');

// User identification
Route::post('identification', 'API\LoginController@identification');

Route::group(['middleware' => 'auth:api'], function(){

    // User authentication
    Route::post('authentication', 'API\LoginController@authentication');

    /*
     ///////////////////////GESTION DES UTILISATEURS/////////////////////////
    */

        /////////////////User sur un user

            //Modification de l'utilisateur
            Route::post('edit_user/{id}', 'API\UserController@edit_user')
            ->where('id', '[0-9]+');

            //lister les utilisateurs
            Route::get('list', 'API\UserController@list');

            //supprimer l'utilisateur
            Route::post('delete/{id}', 'API\UserController@delete')
            ->where('id', '[0-9]+');

            //details d'un utilisateur
            Route::get('details/{id}', 'API\UserController@details_user')
            ->where('id', '[0-9]+');

            //Changer le role d'un utilisateur
            Route::post('edit_role_user/{id}', 'API\UserController@edit_role_user')
            ->where('id', '[0-9]+');

            //Creation d'un utilisateur
            Route::post('register', 'API\UserController@register');

            //Approuver ou desapprouver un utilisateur
            Route::post('edit_user_status/{id}', 'API\UserController@edit_user_status')
            ->where('id', '[0-9]+');

			 //Creation d'un agent de recouvrement
            Route::post('create_recouvreur', 'API\UserController@create_recouvreur');

			 //Changer la zone d'un utilisateur
            Route::post('edit_zone_user/{id}', 'API\UserController@edit_zone_user')
            ->where('id', '[0-9]+');

        /////////////////User sur lui meme

            //details de l'utilisateur
            Route::get('details', 'API\LoginController@details');

			//details de l'utilisateur
            Route::post('update_profile', 'API\LoginController@edit_profile');

            //modifier password
            Route::post('edit_password', 'API\LoginController@reset');

            //deconnexion de l'utilisateur
            Route::post('logout', 'API\LoginController@logout');

            //Changer mon avatar
            Route::post('edit_avatar', 'API\LoginController@update_picture');

			//setting update
            Route::post('edit_setting', 'API\LoginController@update_setting');


    /*
     /////////////////////GESTION DES AGENTS///////////////////////////
    */

        //Creer un Agent
        Route::post('create_agent', 'API\AgentController@store');

        //details d'un Agent
        Route::get('show_agent/{id}', 'API\AgentController@show')
        ->where('id', '[0-9]+');

        //Changer la CNI
        Route::post('edit_cni/{id}', 'API\AgentController@edit_cni');

        //modification d'un Agent
        Route::post('edit_agent/{id}', 'API\AgentController@edit')
        ->where('id', '[0-9]+');

        //modification ddu dossier
        Route::post('edit_folder/{agent}', 'API\AgentController@edit_folder');

        //liste des Agents
        Route::get('list_agents', 'API\AgentController@list');

        //supprimer un Agents
        Route::post('delete_agent/{id}', 'API\AgentController@delete')
        ->where('id', '[0-9]+');

		//Approuver ou desapprouver un agent
		Route::post('edit_agent_status/{id}', 'API\AgentController@edit_agent_status')
		->where('id', '[0-9]+');

		 //Changer la zone d'un agent
		Route::post('edit_zone_agent/{id}', 'API\AgentController@edit_zone_agent')
		->where('id', '[0-9]+');

		// ajouter une puce à un agent
        Route::post('ajouter_puce_agent/{id}', 'API\AgentController@ajouter_puce')
        ->where('id', '[0-9]+');

		// supprimer une puce depuis un agent
        Route::post('delete_puce_agent/{id}', 'API\AgentController@delete_puce')
        ->where('id', '[0-9]+');

    /*
      ///////////////GESTION DES ROLES DES UTILISATEURS///////////////////
    */

        //liste des permisions
        Route::get('permisions_list', 'API\RoleController@permisions_list');

        //Creer un role
        Route::post('store_role', 'API\RoleController@store');

        //details d'un role
        Route::get('show_role/{id}', 'API\RoleController@show')
        ->where('id', '[0-9]+');

        //modification d'un role
        Route::post('edit_role/{id}', 'API\RoleController@update')
        ->where('id', '[0-9]+');

        //supprimer un role
        Route::post('delete_role/{id}', 'API\RoleController@destroy')
        ->where('id', '[0-9]+');

    /*
    //////////////////////GESTION DES FLOTES/////////////////////
    */

        //Creer une flote
        Route::post('store_flote', 'API\FloteController@store');

        //liste des flotes
        Route::get('flote_list', 'API\FloteController@list');

        //details d'une flote'
        Route::get('show_flote/{id}', 'API\FloteController@show')
        ->where('id', '[0-9]+');

        //modification d'une flote
        Route::post('edit_flote/{id}', 'API\FloteController@update')
        ->where('id', '[0-9]+');

        //supprimer une flote
        Route::post('delete_flote/{id}', 'API\FloteController@destroy')
        ->where('id', '[0-9]+');

		// ajouter une puce à une flotte
        Route::post('ajouter_puce_flote/{id}', 'API\FloteController@ajouter_puce')
        ->where('id', '[0-9]+');

		// supprimer une puce à une flotte
        Route::post('delete_puce_flote/{id}', 'API\FloteController@delete_puce')
        ->where('id', '[0-9]+');

            /*
    //////////////////////GESTION DES PUCE /////////////////////
    */
		//liste des types de puces
        Route::get('types_puces_list', 'API\PuceController@types_puces_list');

        //Creer une puce
        Route::post('store_puce', 'API\PuceController@store');

        //liste des puce
        Route::get('puce_list', 'API\PuceController@list');

        //details d'une puce'
        Route::get('show_puce/{id}', 'API\PuceController@show')
        ->where('id', '[0-9]+');

        //modification d'une puce
        Route::post('edit_puce/{id}', 'API\PuceController@update')
        ->where('id', '[0-9]+');

		//modification de l'operateur d'une puce
        Route::post('edit_puce_flote/{id}', 'API\PuceController@update_flote')
        ->where('id', '[0-9]+');

		//modification de l'agent d'une puce
        Route::post('edit_puce_agent/{id}', 'API\PuceController@update_agent')
        ->where('id', '[0-9]+');

        //modification de l'entreprise d'une puce
        Route::post('edit_puce_corporate/{id}', 'API\PuceController@update_corporate')
        ->where('id', '[0-9]+');

        //supprimer une puce
        Route::post('delete_puce/{id}', 'API\PuceController@destroy')
        ->where('id', '[0-9]+');

        //lister les puces d'une flotte
        Route::post('list_puce_flotte/{id}', 'API\PuceController@list_puce_flotte')
        ->where('id', '[0-9]+');

        //lister les puces d'un Agent
        Route::post('list_puce_agent/{id}', 'API\PuceController@list_puce_agent')
        ->where('id', '[0-9]+');

        /*
    //////////////////////Demande de Flotte/////////////////////
    */
          //par un Agent
            //Details d'une demande de flote
            Route::get('detail_demandes_flote/{id}', 'API\DemandeflotteController@show')
            ->where('id', '[0-9]+');

			//Details d'une demande de flote
            Route::post('modifier_demandes_flote/{id}', 'API\DemandeflotteController@modifier')
            ->where('id', '[0-9]+');

			Route::post('annuler_demandes_flote/{id}', 'API\DemandeflotteController@annuler')
            ->where('id', '[0-9]+');

            //Creer une demande de flote
            Route::post('demande_flote', 'API\DemandeflotteController@store');

            //lister mes demandes de flotes peu importe le statut
            Route::get('list_all_demandes_flote', 'API\DemandeflotteController@list_all_status');

        //pour un Agent
			Route::post('annuler_demandes_flote_agent/{id}', 'API\Demande_flote_recouvreurController@annuler')
			->where('id', '[0-9]+');

			//Details d'une demande de flote
            Route::post('modifier_demandes_flote_agent/{id}', 'API\Demande_flote_recouvreurController@modifier')
            ->where('id', '[0-9]+');

            //Creer une demande de flote pour un Agent
            Route::post('demande_flote_agent', 'API\Demande_flote_recouvreurController@store');

			//Details d'une demande de flote
            Route::get('detail_demandes_flote_agent/{id}', 'API\Demande_flote_recouvreurController@show')
            ->where('id', '[0-9]+');

            //lister mes demandes de flotes peu importe le statut
            Route::get('list_demandes_flote', 'API\Demande_flote_recouvreurController@list_all_status');

		//----
			//lister mes demandes de flotes (gestionnaire de flotte ou les admin)
            Route::get('list_demandes_flote_general', 'API\DemandeflotteController@list_demandes_flote_general');

			//modifier d'une demande de flote (gestionnaire de flotte ou les admin)
            Route::post('modifier_demandes_flote_general/{id}', 'API\DemandeflotteController@modifier_general')
            ->where('id', '[0-9]+');
        /*

    //////////////////////Demande de destockage/////////////////////
    */
        //par un Agent
			//Details d'une demande de destockage
            Route::get('detail_demandes_destockage/{id}', 'API\DemandedestockageController@show')
            ->where('id', '[0-9]+');

			//modifier une demande de destockage
            Route::post('modifier_demandes_destockage/{id}', 'API\DemandedestockageController@modifier')
            ->where('id', '[0-9]+');

			Route::post('annuler_demandes_destockage/{id}', 'API\DemandedestockageController@annuler')
            ->where('id', '[0-9]+');

            //Creer une demande de destockage
            Route::post('demande_destockage', 'API\DemandedestockageController@store');

            //lister mes demandes de destockages peu importe le statut
            Route::get('list_all_demandes_destockage', 'API\DemandedestockageController@list_all_status');

        //pour un Agent
			Route::post('annuler_demandes_destockage_agent/{id}', 'API\Demande_destockage_recouvreurController@annuler')
			->where('id', '[0-9]+');

			//Details d'une demande de destockage
            Route::post('modifier_demandes_destockage_agent/{id}', 'API\Demande_destockage_recouvreurController@modifier')
            ->where('id', '[0-9]+');

            //Creer une demande de destockage pour un Agent
            Route::post('demande_destockage_agent', 'API\Demande_destockage_recouvreurController@store');

			//Details d'une demande de destockage
            Route::get('detail_demandes_destockage_agent/{id}', 'API\Demande_destockage_recouvreurController@show')
            ->where('id', '[0-9]+');

            //lister mes demandes de destockage peu importe le statut
            Route::get('list_demandes_destockage', 'API\Demande_destockage_recouvreurController@list_all_status');

            //reponse d'un responsable a une demande
            Route::post('reponse_demandes_destockage/{id}', 'API\Demande_destockage_recouvreurController@reponse');
        /*
    /*
    //////////////////////GESTION DES ZONES DE RECOUVREMENT /////////////////////
    */

        //Creer une zone
        Route::post('store_zone', 'API\ZoneController@store');

        //liste des zone
        Route::get('zone_list', 'API\ZoneController@list');

        //details d'une zone'
        Route::get('show_zone/{id}', 'API\ZoneController@show')
        ->where('id', '[0-9]+');

        //modification d'une zone
        Route::post('edit_zone/{id}', 'API\ZoneController@update')
        ->where('id', '[0-9]+');

        //supprimer une zone
        Route::post('delete_zone/{id}', 'API\ZoneController@destroy')
        ->where('id', '[0-9]+');

        //Attribuer une zonne à un utilisateur
        Route::post('give_zone', 'API\ZoneController@give_zone');

		// ajouter une puce à une flotte
        Route::post('give_zone/{id}', 'API\FloteController@ajouter_puce')
        ->where('id', '[0-9]+');

		// supprimer un agent à une zone
        Route::post('delete_agent_zone/{id}', 'API\ZoneController@delete_agent')
        ->where('id', '[0-9]+');

        // supprimer un recouvreur à une zone
        Route::post('delete_recouvreur_zone/{id}', 'API\ZoneController@delete_recouvreur')
        ->where('id', '[0-9]+');

		// ajouter un agent à un
        Route::post('ajouter_agent_zone/{id}', 'API\ZoneController@ajouter_agent')
        ->where('id', '[0-9]+');

        // ajouter un recouvreur à une zone
        Route::post('ajouter_recouvreur_zone/{id}', 'API\ZoneController@ajouter_recouvreur')
        ->where('id', '[0-9]+');

        // Modifier le responsable d'une zonne
        Route::post('edit_responsable_zone/{id}', 'API\ZoneController@edit_responsable_zone')
        ->where('id', '[0-9]+');




        /*
    //////////////////////Flottages/////////////////////
    */
        //Details d'un Flottage
        Route::get('detail_flottage/{id}', 'API\FlotageController@show')
        ->where('id', '[0-9]+');

        //lister les Flottages peu importe le statut
        Route::get('list_all_flottage', 'API\FlotageController@list_all');

        //lister les Flottages peu importe le statut pour un agent
        Route::get('list_all_flottage_agent/{id}', 'API\FlotageController@list_all_agent')
            ->where('id', '[0-9]+');

        //lister les Flottages peu importe le statut pour un responsable de zone
        Route::get('list_all_flottage_collector/{id}', 'API\FlotageController@list_all_collector')
            ->where('id', '[0-9]+');

        //Creer un Flottage
        Route::post('flottage', 'API\FlotageController@store');

        //lister les Flottages relatifs à une demande precise
        Route::get('list_flottage/{id}', 'API\FlotageController@list_flottage')
        ->where('id', '[0-9]+');

        //Creer un Flottage pour un agent present à l'agence
        Route::post('flottage_express', 'API\FlotageController@flottage_express');

        ///// un responsable de zonne sert la flotte à un Agent un Agent

        //Creer un Flottage d'un responsaple de zone vers un Agent
        Route::post('flottage_by_rz', 'API\Flottage_rzController@flottage_by_rz');

            //lister les Flottages d'un responsable de zonne
        Route::get('list_flottage_rz_by_rz/{id}', 'API\Flottage_rzController@list_flottage_rz_by_rz')
            ->where('id', '[0-9]+');

        //lister les Flottages rz pour un agent precis
        Route::get('list_flottage_rz_by_agent/{id}', 'API\Flottage_rzController@list_flottage_rz_by_agent')
            ->where('id', '[0-9]+');

        //lister tous les Flottages rz
        Route::get('list_all_flottage_by_rz', 'API\Flottage_rzController@list_all_flottage_by_rz');

        //détails d'un flottage effectué par un agent
        Route::get('show_flottage_rz/{id}', 'API\Flottage_rzController@show_flottage_rz')
            ->where('id', '[0-9]+');


                /*//////////////////////Flotage anonyme///////////////*/

        //Creer un Flottage pour un anonyme
        Route::post('flottage_anonyme', 'API\FlotageController@flottage_anonyme');

        //détails d'un flottage anonyme
        Route::get('show_flottage_anonyme/{id}', 'API\FlotageController@show_flottage_anonyme')
            ->where('id', '[0-9]+');

        //lister les Flottages anonymes pour un utilisateur precis
        Route::get('list_flottage_anonyme/{id}', 'API\FlotageController@flottage_anonyme_by_user')
            ->where('id', '[0-9]+');

        //lister tous les Flottages anonymes
        Route::get('list_flottage_anonyme', 'API\FlotageController@list_flottage_anonyme')
            ->where('id', '[0-9]+');



           /*
    //////////////////////Approvisionnement des Puces de ETP/////////////////////
    */
        //traitement d'une demande de destockage (juste pour signaler au système que je traite totalement ou en partie une demande)
        Route::post('traiter_demande', 'API\ApprovisionnementEtpController@traiter_demande');

        //revoquer une demande. elle est effectuée par un responsable de zonne
        Route::post('revoque_demande', 'API\ApprovisionnementEtpController@revoque_demande');

        //Approvisionnement.  faite par le responsable de zone, l'Approvisionnement est de 3 types. par un Agant, le digital partner ou la banque
        Route::post('approvisionnement_etp', 'API\ApprovisionnementEtpController@store');

        //Confirmation par le gestionnaire de flotte, elle atteste avoir recu la flotte
        Route::post('approuve_destockage/{id}', 'API\ApprovisionnementEtpController@approuve')
            ->where('id', '[0-9]+');
        //Confirmation par le gestionnaire de flotte, elle atteste avoir recu l'approviionnement
        Route::post('approuve_approvisionnement/{id}', 'API\ApprovisionnementEtpController@approuve_approvisionnement')
            ->where('id', '[0-9]+');

        //Details d'un approvisionnement
        Route::get('detail_destockage/{id}', 'API\ApprovisionnementEtpController@detail')
        ->where('id', '[0-9]+');

        //lister les approvisionnement
        Route::get('list_approvisionnement', 'API\ApprovisionnementEtpController@list_all');
        Route::get('list_approvisionnement_collector/{id}', 'API\ApprovisionnementEtpController@list_all_collector')
            ->where('id', '[0-9]+');

        // lister les destockage
        Route::get('list_destockage', 'API\ApprovisionnementEtpController@list_all_destockage');
        Route::get('list_destockage_collector/{id}', 'API\ApprovisionnementEtpController@list_all_destockage_collector')
            ->where('id', '[0-9]+');
        Route::get('list_destockage_agent/{id}', 'API\ApprovisionnementEtpController@list_all_destockage_agent')
            ->where('id', '[0-9]+');
        /*
    //////////////////////Recouvrement/////////////////////
    */
        //Creer un Recouvrement
        Route::post('recouvrement', 'API\RecouvrementController@store');

        //Details d'un Recouvrement
        Route::get('detail_recouvrement/{id}', 'API\RecouvrementController@show')
        ->where('id', '[0-9]+');

        //lister les Recouvrement peu importe le statut
        Route::get('list_all_recouvrement', 'API\RecouvrementController@list_all');

        //lister les Recouvrements relatifs à un flottage precis
        Route::get('list_recouvrement/{id}', 'API\RecouvrementController@list_recouvrement')
        ->where('id', '[0-9]+');

        //lister les Recouvrements d'un responsable de zone precis
        Route::get('list_recouvrement_by_rz/{id}', 'API\RecouvrementController@list_recouvrement_by_rz')
        ->where('id', '[0-9]+');

        //lister les Recouvrements d'un agent precis
        Route::get('list_recouvrement_by_agent/{id}', 'API\RecouvrementController@list_recouvrement_by_agent')
        ->where('id', '[0-9]+');

        //Confirmation par le gestionnaire de flotte, elle atteste avoir recu les espèces
        Route::post('approuve_recouvrement/{id}', 'API\RecouvrementController@approuve')
        ->where('id', '[0-9]+');

        /*
    //////////////////////Retour de flote/////////////////////
    */
        //Creer un Retour flotte
        Route::post('retour_flotte', 'API\Retour_flotteController@retour');

        //Details d'un Retour flotte
        Route::get('detail_retour_flotte/{id}', 'API\Retour_flotteController@show')
        ->where('id', '[0-9]+');

        //lister les Retour flotte peu importe le statut
        Route::get('list_all_retour_flotte', 'API\Retour_flotteController@list_all');

        //lister les Retour flotte relatifs à un flottage precis
        Route::get('list_retour_flotte/{id}', 'API\Retour_flotteController@list_retour_flotte')
        ->where('id', '[0-9]+');

        //lister les Retour flotte d'une puce precis
        Route::get('list_retour_flotte_by_sim/{id}', 'API\Retour_flotteController@list_retour_flotte_by_sim')
        ->where('id', '[0-9]+');

        //lister les Recouvrements d'un responsable de zone precis
        Route::get('list_retour_flotte_by_rz/{id}', 'API\Retour_flotteController@list_retour_flotte_by_rz')
            ->where('id', '[0-9]+');

        //lister les Retour flotte d'un agent precis
        Route::get('list_retour_flotte_by_agent/{id}', 'API\Retour_flotteController@list_retour_flotte_by_agent')
        ->where('id', '[0-9]+');

        //Confirmation par le gestionnaire de flotte, elle atteste avoir recu la flotte
        Route::post('approuve_retour_flotte/{id}', 'API\Retour_flotteController@approuve')
        ->where('id', '[0-9]+');

         /*//////////////////////gestion des corporates/////////////////////*/

        //liste des corporates
        Route::get('corporate_list', 'API\CorporateController@list');

        //Creer un corporate
        Route::post('store_corporate', 'API\CorporateController@store');

        //details d'un corporate
        Route::get('show_corporate/{id}', 'API\CorporateController@show')
        ->where('id', '[0-9]+');

        //modification d'un corporate
        Route::post('edit_corporate/{id}', 'API\CorporateController@update')
        ->where('id', '[0-9]+');

        //supprimer un corporate
        Route::post('delete_corporate/{id}', 'API\CorporateController@destroy')
        ->where('id', '[0-9]+');

        //Modifier le dossier d'un corporate
        Route::post('edit_corporate_folder/{id}', 'API\CorporateController@edit_folder')
        ->where('id', '[0-9]+');


        //liste des puce d'une corporate
        Route::get('list_puces_corporate/{id}', 'API\CorporateController@list_puces')
        ->where('id', '[0-9]+');

        // ajouter une puce à une entreprise
        Route::post('ajouter_puce_corporate/{id}', 'API\CorporateController@ajouter_puce')
        ->where('id', '[0-9]+');

        // supprimer une puce à une entreprise
        Route::post('delete_puce_corporate/{id}', 'API\CorporateController@delete_puce')
        ->where('id', '[0-9]+');

         /*//////////////////////Importer les fichiers excels/////////////////////*/

        //importer et comparer le listing pour une puce de flottage
        Route::post('import_flotage', 'API\ImportFlottageController@import_flotage');

        //importer et comparer le listing pour une puce Agent
        Route::post('import_agent', 'API\ImportFlottageController@import_agent');



        /*//////////////////////Noifications/////////////////////*/

            //Recupérer mes notifications non lues
            Route::get('unread_notifications', 'API\NotificationsController@unread_notifications');


            //Recupérer toutes mes notifications
            Route::get('all_notifications', 'API\NotificationsController@all_notifications');


            //marquer comme lue
            Route::get('read_notifications/{id}', 'API\NotificationsController@read_notifications');


            //Supprimer notification
            Route::post('delette_notifications/{id}', 'API\NotificationsController@delette_notifications');


            /*
    //////////////////////Flottage Interne/////////////////////
    */

        //Creer un Flottage Interne, superviseur vers gestionnaire de flotte
        Route::post('flottage_interne', 'API\Flottage_interneController@store');

        //Details d'un Flottage Interne
        Route::get('detail_flottage_interne/{id}', 'API\Flottage_interneController@show')
        ->where('id', '[0-9]+');

        //lister les Flottages Interne
        Route::get('list_all_flottage_interne', 'API\Flottage_interneController@list_all');



        //Creer un Flottage de la gestionnaire de flotte vers Responsable de zonne
        Route::post('flottage_rz', 'API\Flottage_rzController@store');

        //Details d'un Flottage de la gestionnaire de flotte vers Responsable de zonne
        Route::get('detail_flottage_rz/{id}', 'API\Flottage_rzController@show')
            ->where('id', '[0-9]+');

        //lister les Flottages de la gestionnaire de flotte vers Responsable de zonne
        Route::get('list_all_flottage_rz', 'API\Flottage_rzController@list_all');



        //Creer un Flottage d'un superviseur vers un responsable de zone
        Route::post('flottage_interne_rz', 'API\Flottage_interneController@flottage_interne_rz');

        //lister les Flottages d'un superviseur vers un responsable de zone precis
        Route::get('list_all_flottage_interne_by_rz/{id}', 'API\Flottage_interneController@list_all_flottage_interne_by_rz')
            ->where('id', '[0-9]+');


        //Creer un Flottage d'un responsable de zone vers un gestionnaire de flotte
        Route::post('flottage_interne_rz_gf', 'API\Flottage_interneController@flottage_interne_rz_gf');

//todo: new code
        //Creer un Flottage d'un Agent ETP vers un gestionnaire de flotte
        Route::post('flottage_interne_ae_gf', 'API\Flottage_interneController@flottage_interne_ae_gf');



    /*
////////////////////// Gestion des soldes/////////////////////
*/

        //afficher mon solde
        Route::get('mon_solde', 'API\LoginController@solde');

        //recuperer le solde d'un user precis
        Route::get('solde/{id}', 'API\UserController@solde')
        ->where('id', '[0-9]+');

        //Recuperer le solde de tous les agents
        Route::get('agents_soldes', 'API\UserController@agents_soldes');

        //Recuperer le solde de tous les responsables de zonnes
        Route::get('rz_soldes', 'API\UserController@rz_soldes');


        /*
    //////////////////////Encaissement (la gestionnaire de flotte recoit de l'argent d'un agent ou d'un responsable de zonne)/////////////////////
    */

        //creer un Encaissement
        Route::post('encassement', 'API\CaisseController@encassement');

        //Détails d'un encaissement precis
        Route::get('encaissement_details/{id}', 'API\CaisseController@versement_details')
        ->where('id', '[0-9]+');

        //lister les Encaissements
        Route::get('encaissement_list', 'API\CaisseController@encaissement_list');



        /*
    //////////////////////Décaissement (la gestionnaire de flotte donne de l'argent à un responsable de zonne)/////////////////////
    */

        //creer un Decaissement
        Route::post('decaissement', 'API\CaisseController@decaissement');

        //Détails d'un Decaissement precis
        Route::get('decaissement_details/{id}', 'API\CaisseController@versement_details')
        ->where('id', '[0-9]+');

        //lister les Decaissements
        Route::get('decaissement_list', 'API\CaisseController@decaissement_list');

        /*
    //////////////////////passation de service entre les gestionnaires de flotte/////////////////////
    */

        //creer une passation de service
        Route::post('passation', 'API\CaisseController@passation');

        //lister les passation de service
        Route::get('passations_list', 'API\CaisseController@passations_list');


/*
    //////////////////////Attribuer une puce à un responsable de zonne/////////////////////
    */
        // ajouter une puce à un responsable de zonne
        Route::post('ajouter_puce_rz/{id}', 'API\AgentController@ajouter_puce_rz')
        ->where('id', '[0-9]+');

		// supprimer une puce depuis un responsable de zonne
        Route::post('delete_puce_rz/{id}', 'API\AgentController@delete_puce_rz')
        ->where('id', '[0-9]+');

        //todo: new code 09/12/2020, 04 routes créées pour les dépences spontannée par  un utilisateur
    /*
//////////////////////Effectuer une dépense/////////////////////
*/
    //creer une depence
    Route::post('depence', 'API\CaisseController@depence');

    //Détails d'une depence
    Route::get('depence_details/{id}', 'API\CaisseController@depence_details')
        ->where('id', '[0-9]+');

    //lister les depences
    Route::get('depence_list', 'API\CaisseController@depence_list');

    //lister les depences d'un utilisateur precis
    Route::get('depence_list/{id}', 'API\CaisseController@depence_user')
        ->where('id', '[0-9]+');



    //todo: new code 15/12/2020, 04 routes créées pour les Acquisition d'argent ou de flotte ne provenant pas de la gestionnaire de flotte
    /*
//////////////////////Effectuer une acquisition/////////////////////
*/
    //creer une acquisition
    Route::post('acquisition', 'API\CaisseController@acquisition');

    //Détails d'une acquisition
    Route::get('acquisition_details/{id}', 'API\CaisseController@acquisition_details')
        ->where('id', '[0-9]+');

    //lister les acquisitions
    Route::get('acquisition_list', 'API\CaisseController@acquisition_list');

    //lister les acquisitions d'un utilisateur precis
    Route::get('acquisition_list/{id}', 'API\CaisseController@acquisition_user')
        ->where('id', '[0-9]+');




});
