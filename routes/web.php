<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::resource('transaction', 'TransactionController');
Route::resource('commission', 'CommissionController');
Route::resource('type_transaction', 'Type_transactionController');
Route::resource('user', 'UserController');
Route::resource('versement_transaction', 'Versement_transactionController');
Route::resource('flote', 'FloteController');
Route::resource('operation', 'OperationController');
Route::resource('caisse', 'CaisseController');
Route::resource('agent', 'AgentController');
Route::resource('versement', 'VersementController');
Route::resource('motif_operation', 'Motif_operationController');



Route::get('/home', 'HomeController@index')->name('home');
