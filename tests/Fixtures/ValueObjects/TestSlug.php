<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

final class TestSlug extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        // Pas de validation pour permettre les chaînes vides
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Convertit l'objet en chaîne pour le stockage en base de données.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Pour la sérialisation JSON.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
