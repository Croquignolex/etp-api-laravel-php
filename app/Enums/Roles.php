<?php

namespace App\Enums;

use ReflectionClass;

class Roles
{

    const ADMIN = 'ADMIN';
    const AGENT = 'AGENT';
    const GESTION_FLOTTE = 'GESTIONNAIRE DE FLOTTE';
    const RECOUVREUR = 'RESPONSABLE DE ZONNE';
    const SUPERVISEUR = 'SUPERVISEUR';
    const RETOUR_RZ = 'RETOUR RZ';
    const RETOUR_AE = 'RETOUR AE';

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
