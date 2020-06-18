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
        Route::post('edit_cni', 'API\LoginController@edit_cni');

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
        Route::get('delete_role/{id}', 'API\RoleController@destroy')
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




        /*
    //////////////////////Demande de Flotte/////////////////////
    */
    
        //Creer une demande de flote
        Route::post('demande_flote', 'API\DemandeflotteController@store');

        //Lister les demandes de flote
        Route::get('list_demandes_flote', 'API\DemandeflotteController@list_all');

        //Lister mes demandes de flote
        Route::get('list_mes_demandes_flote', 'API\DemandeflotteController@list');

        //Details d'une demande de flote
        Route::get('detail_demandes_flote/{id}', 'API\DemandeflotteController@show')
        ->where('id', '[0-9]+');


        /*
    //////////////////////Demande de destockage/////////////////////
    */
    
        //Creer une demande de destockage
        Route::post('demande_destockage', 'API\DemandedestockageController@store');

        //Lister les demandes de destockage
        Route::get('list_demandes_destockages_flote', 'API\DemandedestockageController@list_all');

        //Lister mes demandes de destockage
        Route::get('list_mes_demandes_destockages', 'API\DemandedestockageController@list');

        //Details d'une demande de destockage
        Route::get('detail_demandes_destockage/{id}', 'API\DemandedestockageController@show')
        ->where('id', '[0-9]+');

});
