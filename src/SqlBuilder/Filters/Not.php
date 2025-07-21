<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

/** A wrapper for another filter to logically invert its result */
class Not implements FiltersInterface{

    public function __construct(protected FiltersInterface $filter){
    }

    public function toSql(): array{
        list($snippet, $params) = $this->filter->toSql();
        return ["NOT $snippet", $params];
    }

    public function match($object):bool{
        return !$this->filter->match($object);
    }

    public function toArraySyntax(): array{
        return ['@not'=>$this->filter->toArraySyntax()];
    }
}