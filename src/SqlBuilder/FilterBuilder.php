<?php
namespace DMJohnson\Miroql\SqlBuilder;

/**
 * Allows building filters using a fluent design or builder pattern. While somewhat more 
 * verbose than the array-based syntax, it can be more readable for complex sets of filters.
 */
class FilterBuilder{
    protected $filters;
    protected $parent;
    protected $logicalOperator;
    public function __construct($parent=null, $logicalOperator = 'AND'){
        $this->parent = $parent;
        $this->filters = [];
        $this->logicalOperator = $logicalOperator;
    }
    /**
     * Convert the filter builder into a filters object
     * 
     * @return Filters\FiltersInterface
     */
    public function build(){
        if (!$this->filters){
            return new Filters\Fixed(true);
        }
        if (count($this->filters) == 1) {
            // A group of one is no group at all
            if (\in_array($this->logicalOperator, ['AND', 'OR'], true)){
                return $this->filters[0];
            }
            if (\in_array($this->logicalOperator, ['NAND', 'NOR'], true)){
                return new Filters\Not($this->filters[0]);
            }
        }
        if ($this->logicalOperator === 'AND'){
            return new Filters\AndGroup($this->filters);
        }
        if ($this->logicalOperator === 'OR'){
            return new Filters\OrGroup($this->filters);
        }
        if ($this->logicalOperator === 'NAND'){
            return new Filters\Not(new Filters\AndGroup($this->filters));
        }
        if ($this->logicalOperator === 'NOR'){
            return new Filters\Not(new Filters\OrGroup($this->filters));
        }
        throw new SqlBuilderException("Unknown logical operator: $this->logicalOperator");
    }

    /**
     * Similar to build, except that if this is a child of another Builder, it will recursively 
     * build the whole chain from the top.
     * 
     * @return Filters\FiltersInterface
     */
    public function build_all(){
        if (isset($this->parent) && $this->parent instanceof FilterBuilder) return $this->end()->build_all();
        else return $this->build();
    }

    /** 
     * Equality test
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function eq($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '=', $value);
        return $this;
    }

    /** 
     * Inequality test
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function ne($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '!=', $value);
        return $this;
    }

    /** 
     * Less-than comparison
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function lt($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '<', $value);
        return $this;
    }

    /** 
     * Greater-than comparison
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function gt($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '>', $value);
        return $this;
    }

    /** 
     * Less-than-or-equal comparison
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function lte($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '<=', $value);
        return $this;
    }

    /** 
     * Greater-than-or-equal comparisons
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function gte($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, '>=', $value);
        return $this;
    }

    /** 
     * SQL `LIKE` operator test
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function like($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'LIKE', $value);
        return $this;
    }

    /** 
     * SQL `NOT LIKE` operator test
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to
     * @return self
     */
    public function not_like($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'NOT_LIKE', $value);
        return $this;
    }

    /** 
     * SQL `LIKE` operator test, with added wildcards
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to. Will be wrapped by % wildcards.
     * @return self
     */
    public function like_in($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'LIKE%%', $value);
        return $this;
    }

    /** 
     * SQL `NOT LIKE` operator test, with added wildcards
     * 
     * @param string $key The column to test
     * @param mixed $value The value to compare to. Will be wrapped by % wildcards.
     * @return self
     */
    public function not_like_in($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'NOT_LIKE%%', $value);
        return $this;
    }

    /** 
     * SQL `IN` operator test
     * 
     * @param string $key The column to test
     * @param array $value The values to compare to
     * @return self
     */
    public function in($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'IN', $value);
        return $this;
    }

    /** 
     * SQL `NOT IN` operator test
     * 
     * @param string $key The column to test
     * @param array $value The values to compare to
     * @return self
     */
    public function not_in($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'NOT_IN', $value);
        return $this;
    }
    
    /** 
     * SQL `BETWEEN` operator test
     * 
     * @param string $key The column to test
     * @param mixed $value1 The first (min) value to compare to
     * @param mixed $value2 The second (max) value to compare to
     * @return self
     */
    public function between($key, $value1, $value2){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'BETWEEN', [$value1, $value2]);
        return $this;
    }

    /** 
     * SQL `REGEXP` operator test
     * 
     * @param string $key The column to test
     * @param array $value The values to compare to
     * @return self
     */
    public function regex($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'REGEXP', $value);
        return $this;
    }

    /** 
     * SQL `REGEXP` operator test
     * 
     * @param string $key The column to test
     * @param array $value The values to compare to
     * @return self
     */
    public function not_regex($key, $value){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\Operator($table, $column, 'NOT_REGEXP', $value);
        return $this;
    }

    /** 
     * Add a test to see if a column is not empty (NULL or '')
     * 
     * @param string $key The column to test
     * @return self
     */
    public function not_empty($key){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\RawSql("(`$table`.`$column` IS NOT NULL AND `$table`.`$column` != '')");
        return $this;
    }

    /** 
     * Add a test to see if a column is empty (NULL or '')
     * 
     * @param string $key The column to test
     * @return self
     */
    public function empty($key){
        list($table, $column) = splitColumn($key);
        $this->filters[] = new Filters\RawSql("(`$table`.`$column` IS NULL OR `$table`.`$column` = '')");
        return $this;
    }

    /** 
     * Add a complex filter using raw SQL.
     * 
     * @param string $sql The SQL snippet to add. Can contain PDO-style placeholders like ":param".
     * @param array<string,mixed> $params Mapping of placeholder names to values.
     * @return self
     */
    public function sql($sql, $params=null){
        $this->filters[] = new Filters\RawSql($sql, $params);
        return $this;
    }

    /** 
     * Add a complex filter using and EXISTS subquery.
     * 
     * @param string|Query $subquery The subquery to use
     * @param ?array<string,mixed> $params Mapping of placeholder names to values.
     * @return self
     */
    public function exists($subquery, $params=null){
        if (\is_null($params)) $params = [];
        if ($subquery instanceof Query) $subquery = $subquery->build($params, true);
        $this->filters[] = new Filters\RawSql("EXISTS($subquery)", $params);
        return $this;
    }

    /**
     * Begin a parenthesized subgroup wherein all filters will be joined by an OR logical operator
     * 
     * @return FilterBuilder
     */
    public function begin_or(){
        return new FilterBuilder($this, 'OR');
    }

    /**
     * Begin a parenthesized subgroup wherein all filters will be joined by an AND logical operator
     * 
     * @return FilterBuilder
     */
    public function begin_and(){
        return new FilterBuilder($this, 'AND');
    }

    /**
     * Begin a parenthesized subgroup wherein all filters will be joined by a NOR logical operator
     * 
     * @return FilterBuilder
     */
    public function begin_nor(){
        return new FilterBuilder($this, 'NOR');
    }

    /**
     * Begin a parenthesized subgroup wherein all filters will be joined by a NAND logical operator
     * 
     * @return FilterBuilder
     */
    public function begin_nand(){
        return new FilterBuilder($this, 'NAND');
    }

    /**
     * Exit the current Builder and return the parent
     * 
     * @return FilterBuilder|Query|null
     */
    public function end(){
        if ($this->parent instanceof FilterBuilder) $this->parent->filters[] = $this->build();
        elseif ($this->parent instanceof Query) $this->parent->where($this->build());
        return $this->parent;
    }

}