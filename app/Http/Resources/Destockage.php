<?php

namespace App\Http\Resources;

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
            'id_recouvreur' => $this->id_recouvreur,
            'puce' => Agent::collection($this->puce),             
            'agent' => Agent::collection($this->agent),
            'type' => $this->type,
            'fournisseur' => $this->fournisseur,
            'recu' => $this->recu,
            'reference' => $this->reference,
            'statut' => $this->statut,
            'montant' => $this->montant,
            'created_at' => $this->created_at,
        ];

    }
}
