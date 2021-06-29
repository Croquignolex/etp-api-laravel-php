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
    const TREASURY_OUT = 'Décaissement externe';
    const TREASURY_IN = 'Encaissement externe';

    const TREASURY_GF_OUT = 'Décaissement GF externe';
    const TREASURY_GF_IN = 'Encaissement GF externe';
    const TREASURY_RZ_OUT = 'Décaissement RZ externe';
    const TREASURY_RZ_IN = 'Encaissement RZ externe';

    const INTERNAL_TREASURY_OUT = 'Décaissement interne';
    const INTERNAL_TREASURY_IN = 'Encaissement interne';
    const INTERNAL_HANDOVER = 'Passation de service';

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
