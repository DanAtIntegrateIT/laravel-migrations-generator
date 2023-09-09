<?php

namespace KitLoong\MigrationsGenerator\Schema\Models;

interface FunctionStored extends Model
{
    /**
     * Get the stored function name.
     */
    public function getName(): string;

    /**
     * Get the stored function create definition.
     */
    public function getDefinition(): string;

    /**
     * Get the stored function drop definition.
     */
    public function getDropDefinition(): string;
}
