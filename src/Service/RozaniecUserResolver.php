<?php

namespace Rozaniec\RozaniecBundle\Service;

class RozaniecUserResolver
{
    /**
     * @param string[] $fullNameFields
     */
    public function __construct(
        private array $fullNameFields = ['firstName', 'lastName'],
    ) {
    }

    public function getFullName(object $user): string
    {
        // Try dedicated getFullName() method first
        if (method_exists($user, 'getFullName') && $user->getFullName()) {
            return $user->getFullName();
        }

        // Build from configured fields
        $parts = [];
        foreach ($this->fullNameFields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($user, $getter)) {
                $val = $user->$getter();
                if ($val) {
                    $parts[] = $val;
                }
            } elseif (property_exists($user, $field)) {
                $ref = new \ReflectionProperty($user, $field);
                $val = $ref->getValue($user);
                if ($val) {
                    $parts[] = $val;
                }
            }
        }

        if ($parts) {
            return implode(' ', $parts);
        }

        // Fallback to Symfony user identifier
        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }

        return '?';
    }
}
