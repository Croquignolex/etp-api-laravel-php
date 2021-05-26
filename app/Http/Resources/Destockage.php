<?php

namespace App\Http\Resources;

use App\User;
use App\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Destockage extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $user =  $this->id_agent === null ? $this->id_agent : $this->agent_user;
        $agent =  $user === null ? $user : $user->agent->first();

        return [
            'id' => $this->id,
            'note' => $this->note,
            'type' => $this->type,
            'fournisseur' => $this->fournisseur,
            'recu' => $this->recu,
            'reference' => $this->reference,
            'statut' => $this->statut,
            'montant' => $this->montant,
            'created_at' => $this->created_at,
            'recouvreur' => User::find($this->id_recouvreur),
            'puce' => $this->puce,
            'operateur' => $this->puce->flote,
            'agent' => $agent,
            'user' => $user,
        ];

    }
}
