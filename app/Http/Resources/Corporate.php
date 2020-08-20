<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Corporate extends JsonResource
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
            'nom' => $this->nom,
            'phone' => $this->phone,
            'responsable' => $this->responsable,
            'dossier' => $this->dossier,
            'adresse' => $this->adresse,
            'numeros_agents' => $this->numeros_agents,
            'description' => $this->description,
        ];
    }
}