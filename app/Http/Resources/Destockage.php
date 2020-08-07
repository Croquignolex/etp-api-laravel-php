<?php

namespace App\Http\Resources;

use App\User;
use Illuminate\Http\Resources\Json\JsonResource;

class Destockage extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        //return parent::toArray($request);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'fournisseur' => $this->fournisseur,
            'recu' => $this->recu,
            'reference' => $this->reference,
            'statut' => $this->statut,
            'montant' => $this->montant,
            'created_at' => $this->created_at,
            'recouvreur' => User::find($this->id_recouvreur),
            'puce' => $this->puce,
            'agent' => $this->agent,
            'user' => User::find($this->agent->id_user),
        ];

    }
}
