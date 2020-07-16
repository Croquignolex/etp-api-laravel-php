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

Route::group(['middleware' => 'auth:api'], function(){
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

        //liste des Agents
        Route::get('list_agents', 'API\AgentController@list');

        //supprimer un Agents
        Route::post('delete_agent/{id}', 'API\AgentController@delete')
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

            //lister mes demandes de flotes en attente
            Route::get('list_mes_demandes_flote', 'API\DemandeflotteController@list');

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

            //lister toutes les demandes de flotes
            Route::get('list_all_status_demande_flote', 'API\Demande_flote_recouvreurController@list_all_status_all_user');

            //lister toutes les demandes de flotes non traitées
            Route::get('list_all_demande_flote', 'API\Demande_flote_recouvreurController@list_all');

            //lister mes demandes de flotes peu importe le statut
            Route::get('list_demandes_flote', 'API\Demande_flote_recouvreurController@list_all_status');

            //lister mes demandes de flotes en attente
            Route::get('list_mes_demandes_flote_agent', 'API\Demande_flote_recouvreurController@list');
        /*
    //////////////////////Demande de destockage/////////////////////
    */

        //pour tous les deux cas 
        
            //Details d'une demande de destockage
            Route::get('detail_demandes_destockage/{id}', 'API\DemandedestockageController@show')
            ->where('id', '[0-9]+'); 

        //par un Agent

            //Creer une demande de destockage
            Route::post('demande_destockage', 'API\DemandedestockageController@store');   
            
            //lister mes demandes de destockage peu importe le statut
            Route::get('list_all_mes_demandes_destockages', 'API\DemandedestockageController@list_all_status');

            //Lister mes demandes de destockage en attente
            Route::get('list_mes_demandes_destockages', 'API\DemandedestockageController@list');


        //pour un Agent

            //Creer une demande de destockage pour un Agent
            Route::post('demande_destockage_agent', 'API\Demande_destockage_recouvreurController@store');

            //lister toutes mes demandes de destockage
            Route::get('list_all_mes_demande_destockage', 'API\Demande_destockage_recouvreurController@list_all_status');

            //lister toutes mes demandes de destockage non traitées
            Route::get('list_mes_demande_destockage', 'API\Demande_destockage_recouvreurController@list_all');

            //lister les demandes de destockage peu importe le statut
            Route::get('list_all_status_demandes_destockage', 'API\Demande_destockage_recouvreurController@list_all_status_all_user');

            //lister les demandes de destockage en attente
            Route::get('list_demandes_destockage_agent', 'API\Demande_destockage_recouvreurController@list');
            
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


        
        /*
    //////////////////////Flottage/////////////////////
    */

        
        //Details d'un Flottage
        Route::get('detail_flottage/{id}', 'API\FlotageController@show')
        ->where('id', '[0-9]+'); 

        //lister les Flottages peu importe le statut
        Route::get('list_all_flottage', 'API\FlotageController@list_all');


        //Creer un Flottage 
        Route::post('flottage', 'API\FlotageController@store');  
            
        //par un agent de recouvrement
        Route::post('flottage', 'API\FlotageController@store_by_ar');       

});
