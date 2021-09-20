<?php

namespace App\Http\Controllers\API;

use App\Movement;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RapportsController extends Controller
{
    /**
     * Mouvements des users
     */
    // SUPERVISEUR
    public function mouvements_utilisateur(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $movements = Movement::where('id_user', $id)
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
     * Raports des users
     */
    // COMPATABLE
    public function reports_utilisateur(Request $request, $id)
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

        $start = Carbon::createFromFormat('d/m/Y', $request->journee, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->journee, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $movements = Movement::where('id_user', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->where('manager', true)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'rapports' => $movements
            ]
        ]);
    }

    /**
     * Transactions des users
     */
    // SUPERVISEUR
    public function transactions_utilisateur(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $transactions = Transaction::where('id_user', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'transactions' => $this->transactionsResponse($transactions)
            ]
        ]);
    }

    /**
     * Mouvements personnel
     */
    // SUPERVISEUR
    public function mouvements_personnel(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $user = Auth::user();

        $movements = Movement::where('id_user', $user->id)
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
     * Transactions personnel
     */
    // SUPERVISEUR
    public function transactions_personnel(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $user = Auth::user();

        $transactions = Transaction::where('id_user', $user->id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'transactions' => $this->transactionsResponse($transactions)
            ]
        ]);
    }

    /**
     * Raports personnel
     */
    // RESPONSABLE DE ZONE
    public function reports_personnel(Request $request)
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

        $start = Carbon::createFromFormat('d/m/Y', $request->journee, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->journee, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $user = Auth::user();

        $movements = Movement::where('id_user', $user->id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->where('manager', true)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'rapports' => $movements
            ]
        ]);
    }

    /**
     * Transactions des puces
     */
    // SUPERVISEUR
    public function transactions_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $transactions = Transaction::where('id_left', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'transactions' => $this->transactionsResponse($transactions)
            ]
        ]);
    }

    /**
     * Transactions des flote
     */
    // SUPERVISEUR
    public function transactions_flote(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'debut' => ['required', 'string'],
            'fin' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $start = Carbon::createFromFormat('d/m/Y', $request->debut, 'Africa/Douala')->startOfDay();
        $end = Carbon::createFromFormat('d/m/Y', $request->fin, 'Africa/Douala')->endOfDay();

        $start->setTimezone('UTC');
        $end->setTimezone('UTC');

        $transactions = Transaction::where('id_operator', $id)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'transactions' => $this->transactionsResponse($transactions)
            ]
        ]);
    }

    // Build transactions return data
    private function transactionsResponse($transactions)
    {
        $returnedTransactions = [];

        foreach($transactions as $transaction)
        {
            $right_sim = $transaction->right_sim;
            $left_sim = $transaction->left_sim;

            $left = is_null($left_sim) ? $transaction->left : $left_sim->numero . ' (' . $left_sim->nom . ')';
            $right = is_null($right_sim) ? $transaction->right : $right_sim->numero . ' (' . $right_sim->nom . ')';

            $returnedTransactions[] = [
                'left' => $left,
                'right' => $right,
                'in' => $transaction->in,
                'out' => $transaction->out,
                'type' => $transaction->type,
                'user' => $transaction->user->name,
                'balance' => $transaction->balance,
                'created_at' => $transaction->created_at,
                'operator' => $transaction->operator->nom,
            ];
        }

        return $returnedTransactions;
    }
}
