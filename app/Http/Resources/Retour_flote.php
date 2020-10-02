<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Retour_flote extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'id_user' => $this->id_user,
            'reference' => $this->reference,
            'montant' => $this->montant,
            'reste' => $this->reste,
            'id_approvisionnement' => $this->id_approvisionnement,
            'statut' => $this->statut,
            'user_destination' => $this->user_destination,
            'user_source' => $this->user_source            
        ];
    }
}