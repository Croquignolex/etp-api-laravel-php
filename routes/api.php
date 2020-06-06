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
Route::get('not_login', 'API\UserController@not_login')->name('not_login');

Route::post('login', 'API\UserController@login');
Route::post('register', 'API\UserController@register');

Route::group(['middleware' => 'auth:api'], function(){
    /*
     ///////////////////////GESTION DES UTILISATEURS/////////////////////////
    */
    //details de l'utilisateur
    Route::get('details', 'API\UserController@details');

    //Modification de l'utilisateur
    // TODO: Is this the current user or any user send in a request?
    Route::post('edit_user/{id}', 'API\UserController@edit_user')->where('id', '[0-9]+');

    //modifier password
    Route::post('edit_password', 'API\UserController@reset');

    //deconnexion de l'utilisateur
    Route::post('logout', 'API\UserController@logout');

    //lister les utilisateurs
    Route::get('list', 'API\UserController@list');

    //supprimer l'utilisateur
    // TODO: Is this the current user or any user send in a request?
    Route::post('delete/{id}', 'API\UserController@delete')->where('id', '[0-9]+');

    /*
     /////////////////////GESTION DES AGENTS///////////////////////////
    */
    //Creer un Agent
    Route::post('create_agent', 'API\AgentController@store');

    //details d'un Agent
    Route::get('show_agent/{id}', 'API\AgentController@show')->where('id', '[0-9]+');

    //modification d'un Agent
    Route::post('edit_agent/{id}', 'API\AgentController@edit')->where('id', '[0-9]+');

    //liste des Agents
    Route::get('list_agents', 'API\AgentController@list');

    //supprimer un Agents
    Route::get('delete_agent/{id}', 'API\AgentController@delete')->where('id', '[0-9]+');

    /*
      ///////////////GESTION DES ROLES DES AGENTS///////////////////
    */
    //liste des permisions
    Route::get('permisions_list', 'API\RoleController@permisions_list');

    //Creer un role
    Route::post('store_role', 'API\RoleController@store');

    //details d'un role
    Route::get('show_role/{id}', 'API\RoleController@show')->where('id', '[0-9]+');

    //modification d'un role
    Route::post('edit_role/{id}', 'API\RoleController@update')->where('id', '[0-9]+');

    //supprimer un role
    Route::get('delete_role/{id}', 'API\RoleController@destroy')->where('id', '[0-9]+');

    /*
    //////////////////////GESTION DES FLOTES/////////////////////
    */
    //Creer une flote
    Route::post('store_flote', 'API\FloteController@store');

    //liste des flotes
    Route::get('flote_list', 'API\FloteController@list');

    //details d'une flote'
    Route::get('show_flote/{id}', 'API\FloteController@show')->where('id', '[0-9]+');

    //modification d'une flote
    Route::post('edit_flote/{id}', 'API\FloteController@update')->where('id', '[0-9]+');

    //supprimer une flote
    Route::post('delete_flote/{id}', 'API\FloteController@destroy')->where('id', '[0-9]+');
});
