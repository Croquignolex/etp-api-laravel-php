<?php

namespace App\Http\Controllers\API;

use App\Movement;
use App\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class RapportsController extends Controller
{
    /**
     * Mouvements des GF
     */
    // SUPERVISEUR
    public function mouvements_gestionnaires(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'journee' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->journee)->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->journee)->endOfDay();

        $movements = Movement::where('id_manager', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'movements' => $movements
            ]
        ]);
    }

    /**
     * Transactions des GF
     */
    // SUPERVISEUR
    public function transactions_gestionnaires(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'journee' => ['required', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->journee)->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->journee)->endOfDay();

        $transactions = Transaction::where('id_manager', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'transactions' => $transactions
            ]
        ]);
    }
}
