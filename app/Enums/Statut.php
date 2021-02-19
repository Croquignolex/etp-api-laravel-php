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
    const COMPLETER = 'complete';
    const ARCHIVER = 'archiver';
    const TERMINEE = 'terminee';
    const EFFECTUER = 'effectue';

    //Opérateurs
    const MTN = 'MTN Cameroun';
    const ORANGE = 'Orange Cameroun';
    const LU = 'lu';

    //types de puce
    const AGENT = 'AGENT';
    const RESOURCE = 'RESSOURCE';
    const FLOTTAGE = 'FLOTTAGE';
    const CORPORATE = 'CORPORATE';
    const FLOTTAGE_SECONDAIRE = 'MASTER SIM';
    const PUCE_RZ = 'RESPONSABLE';


    //types d'approvisionnement'
    const BY_AGENT = "D'un Agent";
    const BY_DIGIT_PARTNER = "D'un Digital Partner";
    const BY_BANK = "De la Banque";



    //types de recouvrement
    const RECOUVREMENT = "Un recouvrement";
    const RETOUR_FLOTTE = "Retour de flotte";

    //Flux d'opération
    const DEPENSE = "Dépense d'argent";
    const ACQUISITION = "Reception d'argent";


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
