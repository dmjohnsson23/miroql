<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

/** Represents a fixed static filter (`WHERE 1` or `WHERE 0`) */
class Fixed implements FiltersInterface{
    protected $value;

    /**
     * @param bool $value True for `WHERE 1`, or false for `WHERE 0`
     */
    public function __construct($value){
        $this->value = $value;
    }
    public function toSql(): array{
        if ($this->value) return ['1', []];
        else return ['0', []];
    }
    public function match($object): bool{
        return $this->value;
    }
    public function toArraySyntax(): bool{
        return $this->value;
    }
}