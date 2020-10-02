<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Corporate extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'phone' => $this->phone,
            'puces' => $this->puces,
            'dossier' => $this->dossier,
            'adresse' => $this->adresse,
            'created_at' => $this->created_at,
            'responsable' => $this->responsable,
            'description' => $this->description,
            'numeros_agents' => $this->numeros_agents,
        ];
    }
}
