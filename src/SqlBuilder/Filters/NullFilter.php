<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

use \DMJohnson\Miroql\SqlBuilder\SQlBuilderException;

/** Filters that should have no effect on the query, but may add parameters */
class NullFilter implements FiltersInterface{
    protected $params;
    public function __construct($params=[]){
        $this->params = $params;
    }
    public function toSql(): array{
        return [null, $this->params];
    }
    /** @throws SQlBuilderException Regardless of circumstances */
    public function match($object): bool{
        throw new SQlBuilderException("Null filters cannot be used to match objects");
    }
    public function toArraySyntax(): array{
        return ['@params'=>$this->params];
    }
}