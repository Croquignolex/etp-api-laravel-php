<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Events\destockageEvent;
use App\Events\NotificationsEvent;
use App\Role;
use App\User;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function event()
    {

        $role = Role::find(3);
        $event = new NotificationsEvent($role->id, ['message' => 'Bon message']);
        broadcast($event)->toOthers();
        return view('home');
    }


    public function logout()
    {
        Auth::logout();
        return view('home');
    }
}
