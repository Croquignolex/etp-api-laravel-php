<?php

namespace App\Enums;

use ReflectionClass;

class Roles
{
    const ADMIN = 'admin';
    const AGENT = 'agent';
    const FOOT_SOLDIER = 'foot-soldier';
    const SUBSCRIBER = 'subscriber';

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