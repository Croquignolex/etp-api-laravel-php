<?php

namespace App\Enums;

use ReflectionClass;

class Transations
{
    const DEMANDE_FLOTTE = 'Demande de flotte';
    const DEMANDE_DESTOCK = 'Demande de déstockage';
    const APPROVISIONNEMENT = 'Approvisionnement';
    const DESTOCKAGE = 'Déstockage';
    const RECOUVREMENT = 'Recouvrement';
    const RETOUR_FLOTTE = 'Retour flotte';

    

    /**
     * Returns the list of all enum variants
     * @return array
     * @throws \ReflectionException
     */
    static public function getList() {
        $className = get_called_class();

        $reflection = new ReflectionClass($className);

        return $reflection->getConstants();
    }
}