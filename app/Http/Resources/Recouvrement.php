<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Recouvrement extends JsonResource
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
            'id_user' => $this->id_user,
            'type_transaction' => $this->type_transaction,
            'montant' => $this->montant,
            'reste' => $this->reste,
            'id_flottage' => $this->id_flottage,
            'statut' => $this->statut,
            'destination' => $this->user_destination,
            'source' => $this->user_source,
            'recu' => $this->recu            
        ];
    }
}

