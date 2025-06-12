<?php

namespace App\Message;

abstract class AbstractMessage
{
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $props = $reflection->getProperties(
            \ReflectionProperty::IS_PRIVATE
                | \ReflectionProperty::IS_PROTECTED
                | \ReflectionProperty::IS_PUBLIC
        );

        $data = [];
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $data[$prop->getName()] = $prop->getValue($this);
        }

        return $data;
    }
}
