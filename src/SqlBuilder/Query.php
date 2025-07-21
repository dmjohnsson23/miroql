<?php
namespace DMJohnson\Miroql\SqlBuilder;

// Could refactor to use this: https://github.com/greenlion/PHP-SQL-Parser
// or this might be better: https://packagist.org/packages/aura/sqlquery
// All of the above would probably be more work to implement than it's worth though; this system is good enough for our use

/**
 * Query builder allowing for programmatically-changeable queries.
 * 
 * Why not use raw SQL? Because, I want the *intent* of the query to be captured
 * in a more machine-readable format, such that a function can easily interpret
 * and modify the query without resorting to an iffy PHP-based SQL query parser.
 * 
 * The query builder is slightly more verbose than the raw query, but I think it
 * provides and adequate enough DSL that it shouldn't be too annoying. And the
 * machine-readable aspect is vital for functionality such as the paginator.
 * 
 * Note that, this class *does not* provide any protection against SQL injection!
 * Do not use this to build a query from user-controlled data. (E.g. this is not 
 * an ad-hoc reporting engine.) This is only for constructing a query. Use filters
 * and prepared statements to protect the query against SQL injection.
 */
class Query{
    const SELECT = 'SELECT';
    const UPDATE = 'UPDATE';
    const DELETE = 'DELETE FROM';
    const INSERT = 'INSERT INTO';
    const JOIN = 'JOIN';
    const LEFT_JOIN = 'LEFT JOIN';
    const RIGHT_JOIN = 'RIGHT JOIN';
    const UNION = 'UNION';
    const UNION_ALL = 'UNION ALL';

    /** @var string $type The type of query (SELECT, UPDATE, etc...) */
    protected $type;
    /** @var string[]|null $columns The list of columns to select in a SELECT query */
    protected $columns;
    /** @var string $table The table to query */
    protected $table;
    /** @var string[][]|null $joins Any tables to join, in the format [$join_type, $join_table, $join_condition] */
    protected $joins;
    /** @var mixed[]|null $filters Filters to apply in the WHERE clause */
    protected $filters;
    /** @var string[]|null $valueMap A mapping of column name to values to ue in INSERT and UPDATE queries */
    protected $valueMap;
    /** @var string|null $order The ORDER BY clause */
    protected $order;
    /** @var string|null $group The GROUP BY clause */
    protected $group;
    /** @var mixed[]|null $group_filters Filters to apply to the HAVING clause */
    protected $group_filters;
    /** @var int|string|array|Paginator $limits The limit clause */
    protected $limits;
    /** @var Query|null $unionPrev A link to the previous query in a chain of UNION queries */
    protected $unionPrev;
    /** @var Query|null $unionNext A link to the next query in a chain of UNION queries */
    protected $unionNext;
    /** @var string|null $unionType The type of UNION to use (UNION or UNION ALL) */
    protected $unionType;

    protected final function __construct($type){
        $this->type = $type;
    }

    /**
     * Begin a SELECT query
     * 
     * @param string[] $columns The columns to fetch. If the array has string indexes, the
     * index will be used as an alias and the value will be the actual column selected.
     * @return self
     */
    public static function select($columns=['*']){
        $instance = new static(Query::SELECT);
        $instance->columns = $columns;
        return $instance;
    }

    /**
     * Begin an UPDATE query
     * 
     * @param string $table The table to update
     * @return self
     */
    public static function update($table){
        $instance = new static(Query::UPDATE);
        $instance->table = $table;
        return $instance;
    }

    /**
     * Begin an INSERT INTO query
     * 
     * @param string $table The table to insert into
     * @return self
     */
    public static function insert($table){
        $instance = new static(Query::INSERT);
        $instance->table = $table;
        return $instance;
    }

    /**
     * Begin a DELETE query
     * 
     * @param string|null $table The table to delete from. Optional, you can specify this via
     * the `from()` method instead.
     * @return self
     */
    public static function delete($table=null){
        $instance = new static(Query::DELETE);
        $instance->table = $table;
        return $instance;
    }

    /**
     * Specify the table this query is operating on
     * 
     * @param string $table The table name
     * @return self For chaining
     */
    public function from($table){
        $this->table = $table;
        return $this;
    }

    /**
     * Specify the first condition in the WHERE clause
     * 
     * @param string|array|FiltersInterface $filtersOrSQL Either a string which will be treated as a raw SQL query, 
     * or an array in filters format.
     * @return self For chaining
     */
    public function where($filtersOrSQL, $params=null){
        if (!isset($this->filters)) $this->filters = [];
        if (is_array($filtersOrSQL) || $filtersOrSQL instanceof Filters\FiltersInterface) $this->filters[] = $filtersOrSQL;
        else $this->filters[] = new Filters\RawSql($filtersOrSQL);
        if (isset($params)) $this->filters[] = new Filters\NullFilter($params);
        return $this;
    }

    public function whereBuilder($logicalOperator='AND'){
        return new FilterBuilder($this, $logicalOperator);
    }

    /**
     * Specify an additional condition in a WHERE or HAVING clause
     * 
     * This should be `and` but that is not a valid identifier, so we're going latin with `et`.
     * 
     * If a HAVING clause has been started, the condition will be assigned to the HAVING clause.
     * Otherwise, the condition will be assigned to the WHERE clause
     * 
     * @deprecated use repeated calls to where() or having() instead
     * 
     * @param string|array|FiltersInterface $filtersOrSQL Either a string which will be treated as a raw SQL query, 
     * or an array in filters format.
     * @return self For chaining
     */
    public function et($filtersOrSQL){
        if (is_null($this->group_filters)){
            if (is_array($filtersOrSQL) || $filtersOrSQL instanceof Filters\FiltersInterface) $this->filters[] = $filtersOrSQL;
            else $this->filters[] = ['@sql'=>$filtersOrSQL];
        }
        else{
            if (is_array($filtersOrSQL) || $filtersOrSQL instanceof Filters\FiltersInterface) $this->group_filters[] = $filtersOrSQL;
            else $this->group_filters[] = ['@sql'=>$filtersOrSQL];
        }
        return $this;
    }

    /**
     * Join another table
     * 
     * @param string $table The name of the table to join
     * @param string $on An SQL snippet for the join condition
     * @return self For chaining
     */
    public function join($table, $on, $alias=null){
        if (is_null($this->joins)) $this->joins = [];
        $this->joins[] = [Query::JOIN, $table, $on, $alias];
        return $this;
    }

    /**
     * Join another table
     * 
     * @param string $table The name of the table to join
     * @param string $on An SQL snippet for the join condition
     * @return self For chaining
     */
    public function leftJoin($table, $on, $alias=null){
        if (is_null($this->joins)) $this->joins = [];
        $this->joins[] = [Query::LEFT_JOIN, $table, $on, $alias];
        return $this;
    }

    /**
     * Join another table
     * 
     * @param string $table The name of the table to join
     * @param string $on An SQL snippet for the join condition
     * @return self For chaining
     */
    public function rightJoin($table, $on, $alias=null){
        if (is_null($this->joins)) $this->joins = [];
        $this->joins[] = [Query::RIGHT_JOIN, $table, $on, $alias];
        return $this;
    }

    /**
     * Map values to be set in an UPDATE or INSERT query.
     * 
     * @param string[] $mapping A map of column names to SQL statements. These can be 
     * column names, prepared statement parameters, or any other arbitrary SQL.
     * @return self For chaining
     */
    public function set($mapping){
        $this->valueMap = $mapping;
        return $this;
    }

    /**
     * Map values to be set in an UPDATE or INSERT query.
     * 
     * @param string[] $mapping A map of column names to SQL statements. These can be 
     * column names, prepared statement parameters, or any other arbitrary SQL.
     * @return self For chaining
     */
    public function values($mapping){
        $this->valueMap = $mapping;
        return $this;
    }

    // TODO `on duplicate key`

    /**
     * Add an ORDER BY clause
     * 
     * If this is a union query, the order by clause is saved on the head (first) query.
     * 
     * @param string $clause The raw SQL of the ORDER BY clause
     * @return self For chaining
     */
    public function orderBy($clause){
        if (isset($this->unionPrev)){
            $this->head()->orderBy($clause);
            return $this;
        }
        $this->order = $clause;
        return $this;
    }

    /**
     * Add a GROUP BY clause
     * 
     * @param string $clause The raw SQL of the GROUP BY clause
     * @return self For chaining
     */
    public function groupBy($clause){
        $this->group = $clause;
        return $this;
    }

    /**
     * Specify the first condition in the HAVING clause
     * 
     * @param string|array|FiltersInterface $filtersOrSQL Either a string which will be treated as a raw SQL query, 
     * or an array in filters format.
     * @return self For chaining
     */
    public function having($filtersOrSQL, $params=null){
        if (!isset($this->group_filters)) $this->group_filters = [];
        if (is_array($filtersOrSQL) || $filtersOrSQL instanceof Filters\FiltersInterface) $this->group_filters[] = $filtersOrSQL;
        else $this->group_filters[] = new Filters\RawSql($filtersOrSQL);
        if (isset($params)) $this->group_filters[] = new Filters\NullFilter($params);
        return $this;
    }

    /**
     * Add a LIMIT clause
     * 
     * If this is a union query, the limit clause is saved on the head (first) query.
     * 
     * @param int|string|array|Paginator|null $limit The limit clause. Any of the following are allowed:
     *  - null: No limit clause is used
     *  - int: A basic limit clause is used to limit to at most this count
     *  - [int, int]: A more complex limit clause with an offset and count
     *  - ['skip' => int, 'count' => int]: The same as above, with named parameters instead of positional
     *  - ['page' => int, 'count' => int]: Convenient syntax for pagination, pages are 1-indexed and contain 
     *    at most `count` items
     *  - Paginator: Use the paginator object to generate the limit clause
     * @param int|null $limit2 if provided, used as the second part of the limit clause. E.g. `limit(1, 2)`
     * is equivalent to `limit([1, 2])`. This parameter is just for the convenience of leaving the [] out.
     * is the count. A plain int or string-based SQL snippet are both allowed.
     * @return self For chaining
     */
    public function limit($limit, $limit2=null){
        if (isset($this->unionPrev)){
            $this->head()->limit($limit, $limit2);
            return $this;
        }
        if (is_null($limit2)) $this->limits = $limit;
        else $this->limits = [$limit, $limit2];
        return $this;
    }

    /**
     * Add an exiting query as a UNION query to the chain after this one
     * 
     * @param Query $query The query to add to the union.
     * @return self The passed query, for chaining.
     */
    public function union($query){
        $this->unionNext = $query;
        $query->unionPrev = $this;
        $this->unionType = Query::UNION;
        return $query;
    }

    /**
     * Add an exiting query as a UNION ALL query to the chain after this one
     * 
     * @param Query $query The query to add to the union.
     * @return self The passed query, for chaining.
     */
    public function unionAll($query){
        $this->unionNext = $query;
        $query->unionPrev = $this;
        $this->unionType = Query::UNION_ALL;
        return $query;
    }

    /**
     * Add a new UNION query and return it
     * 
     * @return self The new query to add to the UNION
     */
    public function unionSelect($columns){
        return $this->union(Query::select($columns));
    }

    /**
     * Add a new UNION ALL query and return it
     * 
     * @return self The new query to add to the UNION
     */
    public function unionAllSelect($columns){
        return $this->unionAll(Query::select($columns));
    }

    /**
     * Get the first query in the chain if this is a chained UNION query
     * 
     * @return self The first query in the chain of UNIONs
     */
    public function head(){
        $head = $this;
        while (isset($head->unionPrev)){
            $head = $head->unionPrev;
        }
        return $head;
    }

    /**
     * Get the last query in the chain if this is a chained UNION query
     * 
     * @return self The last query in the chain of UNIONs
     */
    public function tail(){
        $tail = $this;
        while (isset($tail->unionNext)){
            $tail = $tail->unionNext;
        }
        return $tail;
    }

    public function listUnionQueries(){
        $queries = [];
        $query = $this->head();
        $queries[] = $query;
        while (isset($query->unionNext)){
            $query = $query->unionNext;
            $queries[] = $query;
        }
        return $queries;
    }

    /**
     * Construct the query represented by this object
     * 
     * @param array $params The parameter array. Taken by reference and modified in-place with 
     * any additional params added by the query.
     * @param bool $asUnion Used internally to build UNION queries, you should never
     * use this parameter yourself.
     * @return string The constructed query
     */
    public function build(&$params, $asUnion=false){
        if (!$asUnion && isset($this->unionPrev)) return $this->head()->build($params);
        $query = $this->buildMainClause();
        if ($this->type === Query::SELECT)
            $query .= $this->buildJoinClause();
        if ($this->type === Query::INSERT)
            $query .= $this->buildValuesClause();
        if ($this->type === Query::UPDATE)
            $query .= $this->buildSetClause();
        if ($this->type !== Query::INSERT)
            $query .= $this->buildWhereClause($params);
        if ($this->type === Query::SELECT){
            $query .= $this->buildGroupByClause();
            $query .= $this->buildHavingClause($params);
        }
        if ($asUnion) return $query;
        $unionQuery = $this;
        while (isset($unionQuery->unionNext)){
            $query .= "$unionQuery->unionType ";
            $unionQuery = $unionQuery->unionNext;
            $query .= $unionQuery->build($params, true);
        }
        if ($this->type === Query::SELECT || $this->type === Query::DELETE){
            $query .= $this->buildOrderByClause();
            $query .= $this->buildLimitClause($params);
        }
        return $query.';';
    }

    /**
     * Build an alternate form of the query to get the count that would be returned by
     * this query without the LIMIT clause.
     * 
     * @param array $params The parameter array. Taken by reference and modified in-place with 
     * any additional params added by the query.
     * @param bool $asUnion Used internally to build UNION queries, you should never 
     * use this parameter yourself.
     * @return string The constructed query
     */
    public function buildForCount(&$params, $asUnion=false){
        if ($this->type !== Query::SELECT) throw new \LogicException("Can only get the count of SELECT queries");
        if (!$asUnion && isset($this->unionPrev)) return $this->head()->buildForCount($params);
        if ($asUnion || isset($this->unionNext) || isset($this->group)){
            // These queries are complex to get an accurate count for. We'll have to use a subquery.
            $query = $this->buildMainClause();
            $query .= $this->buildJoinClause();
            $query .= $this->buildWhereClause($params);
            $query .= $this->buildGroupByClause();
            $query .= $this->buildHavingClause($params);
            if ($asUnion) return $query;
            $unionQuery = $this;
            while (isset($unionQuery->unionNext)){
                $query .= "$unionQuery->unionType ";
                $unionQuery = $unionQuery->unionNext;
                $query .= $unionQuery->buildForCount($params, true);
            }
            return "SELECT COUNT(*) FROM ($query) AS for_count;";
        }
        else{
            // Basic easy query with no unions or GROUP BY, so we can just use a COUNT
            $query = "SELECT COUNT(*) FROM $this->table ";
            $query .= $this->buildJoinClause();
            $query .= $this->buildWhereClause($params);
            $query .= $this->buildGroupByClause();
            $query .= $this->buildHavingClause($params);
            $this->removeUnusedParams($query, $params);
            return $query.';';
        }
    }

    protected function buildMainClause(){
        if ($this->type === Query::SELECT){
            $columns = [];
            foreach ($this->columns as $key => $value){
                if ($value === 'NULL'){
                    // No change needed
                }
                elseif (preg_match('/^[a-zA-z0-9_]+(\.[a-zA-Z0-9_]+)*$/', $value)){
                    $value = explode('.', $value);
                    $value = implode('`.`', $value);
                    $value = "`$value`";
                }
                if (is_string($key)) $columns[] = "$value AS `$key`";
                else $columns[] = $value;
            }
            $columns = implode(', ', $columns);
            $query = "SELECT $columns FROM $this->table ";
        }
        else{
            $query = "$this->type $this->table ";
        }
        return $query;
    }

    protected function buildJoinClause(){
        if (!isset($this->joins)) return '';
        $query = '';
        foreach ($this->joins as $join){
            list($join, $table, $condition, $alias) = $join;
            $query .= "$join $table ";
            if ($alias) $query .= "AS `$alias` ";
            if ($condition) $query .= "ON $condition ";
        }
        return $query;
    }

    protected function buildValuesClause(){
        if (is_null($this->valueMap))
            throw new \LogicException('Cannot build incomplete query, missing VALUES clause');
        $keys = implode(', ', array_keys($this->valueMap));
        $values = implode(', ', array_values($this->valueMap));
        return "($keys) VALUES ($values) ";
    }

    protected function buildSetClause(){
            if (is_null($this->valueMap))
                throw new \LogicException('Cannot build incomplete query, missing SET clause');
            $set = [];
            foreach ($this->valueMap as $key=>$value) $set[] = "$key = $value";
            $set = implode(', ', $set);
            return "SET $set";
    }

    protected function buildWhereClause(&$params){
        if (empty($this->filters)) return '';
        $keyword = 'WHERE';
        $query = '';
        foreach ($this->filters as $filters){
            if (empty($filters)) continue;
            list($filterSnippet, $filterParams) = Filters::toSql($filters);
            $params = array_merge($params, $filterParams);
            if ($filterSnippet === '' || is_null($filterSnippet)) continue;
            if ($filterSnippet[0] != '(') $filterSnippet = "($filterSnippet)";
            $query .= "$keyword $filterSnippet ";
            $keyword = 'AND';
        }
        return $query;
    }

    protected function buildHavingClause(&$params){
        if (empty($this->group_filters)) return '';
        $keyword = 'HAVING';
        $query = '';
        foreach ($this->group_filters as $filters){
            if (empty($filters)) continue;
            list($filterSnippet, $filterParams) = Filters::toSql($filters);
            $params = array_merge($params, $filterParams);
            if (empty($filterSnippet)) continue;
            if ($filterSnippet[0] != '(') $filterSnippet = "($filterSnippet)";
            $query .= "$keyword $filterSnippet ";
            $keyword = 'AND';
        }
        return $query;
    }

    protected function buildOrderByClause(){
        if (empty($this->order)) return '';
         return "ORDER BY $this->order ";
    }

    protected function buildGroupByClause(){
        if (empty($this->group)) return '';
         return "GROUP BY $this->group ";
    }

    protected function buildLimitClause(&$params){
        $limit = $this->limits;
        if ($limit instanceof Paginator) $limit = $limit->paginate();
        if (empty($limit)) return '';
        if (is_int($limit)){
            $params['limitClauseCount'] = $limit;
            return 'LIMIT :limitClauseCount ';
        }
        if (is_string($limit)) return "LIMIT $limit "; // Raw SQL
        if (is_array($limit)){
            if (isset($limit[0]) && isset($limit[1])){
                // skip and limit positional params
                $params['limitClauseSkip'] = $limit[0];
                $params['limitClauseCount'] = $limit[1];
                return 'LIMIT :limitClauseSkip, :limitClauseCount ';
            }
            if (isset($limit['skip']) && isset($limit['count'])){
                // skip and limit named params
                $params['limitClauseSkip'] = $limit['skip'];
                $params['limitClauseCount'] = $limit['count'];
                return 'LIMIT :limitClauseSkip, :limitClauseCount ';
            }
            if (isset($limit['page']) && isset($limit['count'])){
                // page and count named params
                $params['limitClauseSkip'] = ($limit['page'] - 1) * $limit['count'];
                $params['limitClauseCount'] = $limit['count'];
                return 'LIMIT :limitClauseSkip, :limitClauseCount ';
            }
        }
    }

    protected function removeUnusedParams($query, &$params){
        $unused = [];
        foreach ($params as $key=>$value){
            if (!str_contains($query, ":$key")){
                $unused[] = $key;
            }
        }
        // Iterate twice to avoid messing up the first for loop's internal pointer
        foreach ($unused as $key){
            unset($params[$key]);
        }
        return $unused;
    }
}