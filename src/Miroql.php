<?php
namespace DMJohnson\Miroql;

use \DMJohnson\Miroql\SqlBuilder\{Query,FilterBuilder};

/**
 * Short for "Mango-inspired report-oriented query language". "Miroql" is pronounced similarly to 
 * "Miracle".
 * 
 * As the name implies, it is ispired by Mango queries, which are documented by
 * [Apache CouchDB](https://docs.couchdb.org/en/stable/api/database/find.html).)
 */
class Miroql{
    /** @var array<string,JoinInfo> $joins */
    private array $joins = [];
    /** @var array<string,TableTranslation> $tables */
    private array $tables = [];
    /** @var array<string,array> $selectedColumnInfo */
    private array $selectedColumnInfo = [];
    public Translator $nameTranslator;

    public function __construct(?Translator $nameTranslator = null){
        if (isset($nameTranslator)) $this->nameTranslator = $nameTranslator;
        else $this->nameTranslator = new DefaultTranslator();
    }

    /**
     * Given a Miroql query, execute a PDO statement
     * 
     * @return \PDOStatement An executed statement, ready for results to be fetched
     */
    function executeQueryPdo($pdo, array $miroql, string $baseTable){
        $query = $this->makeQuery($miroql, $baseTable);
        $params = [];
        $sql = $query->build($params);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Given a Miroql query, return an SQL `Query` object
     */
    function makeQuery(array $miroql, string $baseTable):Query{
        // Find the selected fields
        if (empty($miroql['fields'])) throw new MiroqlException('No fields selected');
        $query = Query::select($this->makeSelectedColumns($miroql['fields'], $baseTable));
        // Look up the base table
        $translated = $this->nameTranslator->baseTableName($baseTable);
        if (is_null($translated)){
            throw new MiroqlException("Base table '$baseTable' not found");
        }
        $this->joins = \array_merge($this->joins, $translated->joins??[]);
        $this->tables[$translated->alias] = $translated;
        $realName = $translated->table;
        // $realName and $baseTable should both be safe thanks to the name translator
        $query->from("$realName AS `$baseTable`");
        // Applu filters, sort, and group
        if (!empty($miroql['selector'])){
            $filterBuilder = $query->whereBuilder();
            $this->makeFilters($miroql['selector'], $baseTable, $filterBuilder);
            $filterBuilder->end();
        }
        if (!empty($miroql['sort'])){
            $query->orderBy(
                $this->makeOrderBy($miroql['sort'], $baseTable)
            );
        }
        if (!empty($miroql['group'])){
            $query->groupBy(
                $this->makeGroupBy($miroql['group'], $baseTable)
            );
        }
        // Look for limit or skip/limit
        $limit = null;
        if (isset($miroql['limit']) && isset($miroql['skip'])){
            $limit = [
                'count'=>(int)$miroql['limit'],
                'skip'=>(int)$miroql['skip'],
            ];
        }
        elseif (isset($miroql['limit'])){
            $limit = (int)$miroql['limit'];
        }
        $query->limit($limit);
        // Add whatever join tables are needed based on all the above method calls
        $this->addJoins($query, $miroql['join'] ?? 'inner');
        // See if any additional filters are needed
        $extraFilters = $this->nameTranslator->getFilters($this->tables);
        if (isset($extraFilters)) $query->where($extraFilters);
        return $query;
    }

    /**
     * @internal
     */
    function makeSelectedColumns(array $fields, string $baseTable):array{
        $selectedColumns = [];
        foreach ($fields as $field){
            if (\is_array($field)){
                // Process aggregate operators
                foreach ($field as $key=>$value){
                    // Array should contain only one key/value pair, but a loop is the easy way to go
                    $alias = $key.'.'.$value; // TODO allow explicit alias
                    $sqlName = $this->findSqlName($baseTable, $value);
                    $sql = match ($key){
                        '$value'=>$sqlName,
                        '$count'=>"COUNT($sqlName)",
                        '$count-distinct'=>"COUNT(DISTINCT $sqlName)",
                        '$concat'=>"GROUP_CONCAT($sqlName ORDER BY $sqlName SEPARATOR ', ')",
                        '$concat-distinct'=>"GROUP_CONCAT(DISTINCT $sqlName ORDER BY $sqlName SEPARATOR ', ')",
                        '$distinct'=>"DISTINCT $sqlName",
                        '$sum'=>"SUM($sqlName)",
                        '$avg'=>"AVG($sqlName)",
                        '$min'=>"MIN($sqlName)",
                        '$max'=>"MAX($sqlName)",
                        default=>throw new MiroqlException("Unknown or unsupported aggregate function: $key")
                    };
                    $selectedColumns[$alias] = $sql;
                    $this->selectedColumnInfo[$alias] = [];
                }
            }
            else{
                // Plain string
                $alias = $field; // TODO allow explicit alias
                // TODO could also allow aggregate functions in key as $max.table.column
                $sqlName = $this->findSqlName($baseTable, $field);
                $selectedColumns[$alias] = $sqlName;
                $this->selectedColumnInfo[$alias] = [];
            }
        }
        return $selectedColumns;
    }

    /**
     * @param array $selector The selector to parse into filters
     * @param string $baseTable The base table the report is running against
     * @param ?string $fieldName Used to pass the current field name on recusive calls; not used on the top-level call
     * @param ?FilterBuilder $builder Used to pass the current FilterBuilder on recusive calls; not used on the top-level call
     * 
     * @internal
     */
    function makeFilters(array $selector, string $baseTable, ?FilterBuilder $builder=null, ?string $fieldName = null):FilterBuilder{
        if (\is_null($builder)) $builder = new FilterBuilder();
        foreach($selector as $key=>$value){
            if (\preg_match('/^\$(n?(?:and|or))$/', $key, $match)){
                // Logical operator groups
                $subBuilder = new FilterBuilder($builder, \strtoupper($match[1]));
                foreach ($value as $subValue){
                    $this->makeFilters($subValue, $baseTable, $subBuilder, $fieldName);
                }
                $subBuilder->end();
            }
            elseif ($key === '$not'){
                // Simple NOT operation (same as NAND group with only one item)
                $subBuilder = $builder->begin_nand();
                $this->makeFilters($value, $baseTable, $subBuilder, $fieldName);
                $subBuilder->end();
            }
            elseif (\preg_match('/^\$((?:eq|neq?|lte?|gte?)|(?:not-)?(?:in|empty|like|contains|regex))$/', $key, $match)){
                if (\is_null($fieldName)){
                    throw new MiroqlException('Conditional operation '.json_encode([$key=>$value]).' must be nested underneath a field ');
                }
                $sqlFieldName = $this->findSqlName($baseTable, $fieldName);
                // Explicit operator
                switch ($match[1]){
                    case 'eq':
                        $builder->eq($sqlFieldName, $value);
                        break;
                    case 'ne':
                    case 'neq':
                        $builder->ne($sqlFieldName, $value);
                        break;
                    case 'lt':
                        $builder->lt($sqlFieldName, $value);
                        break;
                    case 'lte':
                        $builder->lte($sqlFieldName, $value);
                        break;
                    case 'gt':
                        $builder->gt($sqlFieldName, $value);
                        break;
                    case 'gte':
                        $builder->gte($sqlFieldName, $value);
                        break;
                    case 'in':
                        $builder->in($sqlFieldName, $value);
                        break;
                    case 'not-in':
                        $builder->not_in($sqlFieldName, $value);
                        break;
                    case 'empty':
                        if ($value) $builder->empty($sqlFieldName);
                        else $builder->not_empty($sqlFieldName);
                        break;
                    case 'not-empty':
                        if (!$value) $builder->empty($sqlFieldName);
                        else $builder->not_empty($sqlFieldName);
                        break;
                    case 'like':
                        $builder->like($sqlFieldName, $value);
                        break;
                    case 'not-like':
                        $builder->not_like($sqlFieldName, $value);
                        break;
                    case 'contains':
                        $builder->like_in($sqlFieldName, $value);
                        break;
                    case 'not-contains':
                        $builder->not_like_in($sqlFieldName, $value);
                        break;
                    case 'regex':
                        $builder->regex($sqlFieldName, $value);
                        break;
                    case 'not-regex':
                        $builder->not_regex($sqlFieldName, $value);
                        break;
                }
            }
            elseif (\preg_match('/^[a-zA-Z_]+(\.[a-zA-Z_]+)?$/', $key)){
                // Plain string field name key
                if (\is_null($fieldName)){
                    // Dotted field, like {"cvso.f_name": "John"}, or implicit field, like {"f_name": "John"}
                    $newFieldName = $key;
                }
                else{
                    // Nested field, like {"cvso": {"f_name": "John"}}; convert to dotted
                    $newFieldName = $fieldName.'.'.$key;
                }
                if (\is_scalar($value) || \is_null($value)){
                    // Implicit equality
                    $newFieldName = $this->findSqlName($baseTable, $newFieldName);
                    $builder->eq($newFieldName, $value);
                }
                else{
                    // Explicit operator or nested field below us
                    $this->makeFilters($value, $baseTable, $builder, $newFieldName);
                }
            }
            else{
                throw new MiroqlException("Unknown or unparsable key '$key'");
            }
        }
        return $builder;
    }

    /**
     * Make an SQL snippet for a SORT BY clause
     * 
     * @internal
     */
    function makeOrderBy($sort, $baseTable):string{
        if (\is_string($sort)){
            // A bare field name: e.g. "veteran.l_name"
            return $this->findSqlName($baseTable, $sort);
        }
        if (\array_is_list($sort)){
            // Sort by multiple columns
            $sql = [];
            foreach ($sort as $column){
                $sql[] = $this->makeOrderBy($column, $baseTable);
            }
            return implode(', ', $sql);
        }
        // A field with explicit ordering, e.g. {"veteran.l_name": "asc"}
        /** @var non-empty-array $sort Should contain exactly one key/value pair */
        foreach ($sort as $fieldName=>$sortDirection){
            $sql = $this->findSqlName($baseTable, $fieldName);
            if ($sortDirection === 'desc') $sql .= ' DESC';
            elseif ($sortDirection === 'asc') $sql .= ' ASC';
            else throw new MiroqlException("Unknown sort direction '$sortDirection'");
            return $sql;
        }
    }

    /**
     * Make an SQL snippet for a GROUP BY clause
     * 
     * @internal
     */
    function makeGroupBy($group, $baseTable):string{
        if (\is_string($group)){
            // A bare field name: e.g. "veteran.l_name"
            return $this->findSqlName($baseTable, $group);
        }
        if (\array_is_list($group)){
            // Group by multiple columns
            $sql = [];
            foreach ($group as $column){
                $sql[] = $this->makeGroupBy($column, $baseTable);
            }
            return implode(', ', $sql);
        }
        throw new MiroqlException('Invalid value for group: '.json_encode($group));
    }

    /**
     * Given the public name, return the real name to use in SQL queries
     * 
     * @internal
     */
    function findSqlName($baseTable, $userSuppliedKey):string{
        if (\str_contains($userSuppliedKey, '.')){
            // Fields contains explicit base table
            $key = $userSuppliedKey;
        }
        else{
            // Add base table implicitly
            $key = $baseTable.'.'.$userSuppliedKey;
        }
        // Translate the name to get real DB values and relationship info
        $translated = $this->nameTranslator->bodyFieldName($key, $baseTable);
        // Translator should return null for tables/columns that don't exist
        if (is_null($translated)) throw new MiroqlException("Key $key not a recognized field name");
        // Look for any new joins to add to the list of joined tables
        if (!empty($translated->table->joins)){
            $this->joins = \array_merge($this->joins, $translated->table->joins);
        }
        $this->tables[$translated->table->alias] = $translated->table;
        return $translated->selector;
    }

    /**
     * Add join conditions to a query, based on the fields discovered by previous calls
     * 
     * @internal
     */
    function addJoins(Query $query, $joinType='inner'):void{
        foreach ($this->joins as $alias=>$join){
            switch ($join->type??$joinType){
                case 'inner':
                    $query->join($join->table, $join->condition, $alias);
                    break;
                case 'left':
                    $query->leftJoin($join->table, $join->condition, $alias);
                    break;
                case 'right':
                    $query->rightJoin($join->table, $join->condition, $alias);
                    break;
                default:
                    throw new MiroqlException("Unknown join type '$joinType'");
            }
        }
    }
}
