<?php

namespace App\Enums;

use ReflectionClass;

class Statut
{
    //Statut
    const EN_ATTENTE = 'en-attente';
    const EN_COURS = 'en-cours';
    const APPROUVE = 'approuve';
    const ANNULE = 'annule';
    const DECLINE = 'decline';
    const COMPLETER = 'completer';
    const ARCHIVER = 'archiver';
    const TERMINEE = 'terminee';
    const EFFECTUER = 'effectue';

    //Opérateurs
    const MTN = 'MTN Cameroun';
    const ORANGE = 'Orange Cameroun';
    const LU = 'lu';

    //types de puce
    const AGENT = 'Puce Agent';
    const ETP = 'Puce Agent ETP';
    const FLOTTAGE = 'Puce Flottage';
    const FLOTTAGE_SECONDAIRE = 'Puce Flotage Sencondaire';

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