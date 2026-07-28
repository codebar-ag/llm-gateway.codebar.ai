<?php

namespace App\Traits;

trait HasNovaEnumLabelTrait
{
    /**
     * Get the label for the enum case.
     * This method should be implemented by the enum using this trait.
     */
    abstract public function getLabel(): string;

    /**
     * Nova Integration - alias for getLabel()
     */
    public function label(): string
    {
        return $this->getLabel();
    }
}
