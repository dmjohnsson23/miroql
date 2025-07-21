<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

/** A parenthesized subgroup of filters wherein the items are joined by a logical AND */
class AndGroup implements FiltersInterface{
    protected $filters;
    /**
     * @param FiltersInterface[] $filters
     */
    public function __construct($filters){
        $this->filters = $filters;
    }

    public function toSql(): array{
        if (empty($this->filters)) return ['1', []];
        $snippets = [];
        $params = [];
        foreach ($this->filters as $filter){
            list($snippet, $subParams) = $filter->toSql();
            if (!empty($snippet)) $snippets[] = $snippet;
            $params = array_merge($params, $subParams);
        }
        return ['('.implode(' AND ', $snippets).')', $params];
    }

    public function match($object):bool{
        if (empty($this->filters)) return true;
        foreach ($this->filters as $filter){
            if (!$filter->match($object)){
                // Short circuit
                return false;
            }
        }
        // We never short-circuited
        return true;
    }

    public function toArraySyntax(): array{
        $parts = [];
        foreach ($this->filters as $filter){
            $parts[] = $filter->toArraySyntax();
        }
        return ['@and'=>$parts];
    }
}