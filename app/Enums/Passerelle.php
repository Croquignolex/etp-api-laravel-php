<?php

namespace App\Enums;

use ReflectionClass;

class Passerelle
{
    const OM = 'om';
    const MOMO = 'momo';

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