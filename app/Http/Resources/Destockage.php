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
        //return parent::toArray($request);
        $agent =  $this->id_agent === null ? $this->id_agent : User::find($this->id_agent)->agent->first();
        $user =  $this->id_agent === null ? $this->id_agent : User::find($this->id_agent);

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
            'agent' => $agent,
            'user' => $user,
        ];

    }
}
