<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

use \DMJohnson\Miroql\SqlBuilder\SQlBuilderException;

/** Raw SQL as a filters object */
class RawSql implements FiltersInterface{
    protected $sql;
    protected $params;
    /** 
     * @param literal-string $sql 
     * @param array<string,mixed> $params
     */
    public function __construct($sql, $params=[]){
        $this->sql = $sql;
        $this->params = $params;
    }
    public function toSql(): array{
        return [$this->sql, $this->params];
    }
    /** @throws SQlBuilderException Regardless of circumstances */
    public function match($object): bool{
        throw new SQlBuilderException("SQL filters cannot be used to match objects");
    }
    public function toArraySyntax(): array{
        return [
            '@sql'=>$this->sql,
            '@params'=>$this->params,
        ];
    }
}