<?php

namespace App\Enums;

use ReflectionClass;

class Roles
{
    const ADMIN = 'Admin';
    const AGENT = 'Agent';
    const GESTION_FLOTTE = 'Gestionnaire de flotte';
    const RECOUVREUR = 'Responsable de zone';
    const SUPERVISEUR = 'Superviseur';

    

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