<?php

namespace App\Enums;

use ReflectionClass;

class Alerte
{
    const PRIX = 1;
    

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