<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Puce extends JsonResource
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
            'name' => $this->nom,
            'id_flotte' => $this->id_flotte,
            'id_agent' => $this->id_agent,
            'numero' => $this->numero,
            'reference' => $this->reference,
            'type' => $this->type,
            'corporate' => $this->corporate,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];

    }
}
