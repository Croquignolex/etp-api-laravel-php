<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Agent extends JsonResource
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
            'img_cni' => $this->img_cni,
            'dossier' => $this->dossier,
            'img_cni_back' => $this->img_cni_back,
            'point_de_vente' => $this->point_de_vente,
            'reference' => $this->reference,
            'taux_commission' => $this->taux_commission,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'id_creator' => $this->id_creator,
            'id_user' => $this->id_user,
            'created_at' => $this->created_at,
        ];
        
    }
}
