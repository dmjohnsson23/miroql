<?php
namespace DMJohnson\Miroql\SqlBuilder;

use DMJohnson\Miroql\SqlBuilder\Filters\{AndGroup,FiltersInterface,Fixed,Not,NullFilter,Operator,OrGroup,RawSql};

/**
 * Module for using filters. Filters can be applied to SQL queries or directly matched against objects.
 * 
 * Filters are defined as arrays with a specific format. The keys or this array are strings in the form 
 * "$columnName $operator", where the operator is optional and will be assumed '=' if not provided. The 
 * values are the user-supplied values for the given column to search against.
 * 
 * The following operators are supported: [=, !=, >, <, >=, <=, LIKE, LIKE%%]. The special LIKE%% 
 * operator surrounds the value string with %%, but is otherwise a normal LIKE.
 * 
 * The IN and NOT_IN (note the added underscore) operators are also supported, and take an array of
 * parameter values
 * 
 * There are also special directives which can be supplied as keys. These all begin with an '@' sign:
 *   * @sql: The value is a literal SQL snippet to be injected. Obviously, be careful with this. 
 *     You should not use this directive if the value comes from user input; as you are deliberately 
 *     bypassing SQL injection protections
 *   * @params: The value is an array of parameters that will be added to the returned parameter array.
 *     This is useful to supply values for parameters which were added with an @sql directive.
 *   * @(or): The value is an array using the same structure as the parent $filters array. These will
 *     be OR'd together and enclosed in parenthesis in the main snippet
 *   * @(and): The value is an array using the same structure as the parent $filters array. These will
 *     be AND'd together and enclosed in parenthesis in the main snippet
 * 
 * An optional label can be added to the key after an additional space (after the operator or after 
 * the directive) to make keys unique in the event you have keys with the potential to clash. The
 * label will be completely ignored and is only used to prevent key clashes.
 * 
 * You may pass more than one filters array as an array of arrays. The will simply be merged together
 * as if you had passed them in as one big array.
 */
abstract class Filters{
    /** 
     * Create a filters object from the given params
     * 
     * @param array<int|literal-string,mixed>|bool|null|literal-string|FiltersInterface|FilterBuilder $filters
     * @return FiltersInterface
     */
    public static function create($filters){
        if ($filters instanceof FiltersInterface){
            return $filters;
        }
        if ($filters instanceof FilterBuilder){
            return $filters->build_all();
        }
        if (is_array($filters) || is_bool($filters) || is_string($filters)){
            $filters = static::parseArray($filters);
            if (count($filters) == 1) return $filters[0];
            if ($filters) return new AndGroup($filters);
            return new Fixed(true); // No filters
        }
        if (is_null($filters)){
            return new Fixed(true); // No filters
        }
        /** @phpstan-ignore deadCode.unreachable */
        throw new SqlBuilderException("Cannot interpret as filters: ".var_export($filters, true));
    }

    /** 
     * Use a builder pattern to build a filters object, instead of the normal array syntax.
     * @return FilterBuilder The builder object
     */
    public function build(){
        return new FilterBuilder();
    }

    /** 
     * Use a builder pattern to build a filters object, instead of the normal array syntax.
     * @return FilterBuilder The builder object
     */
    public function build_or(){
        return new FilterBuilder(null, 'OR');
    }

    /**
     * Parse an array in filters format into an actual filters object
     * 
     * @param array<int|literal-string,mixed>|bool|literal-string $array And array in the filters format.
     * @return FiltersInterface[] An array of actual filters objects
     */
    protected static function parseArray($array){
        if ($array === true) return [new Fixed(true)];
        if ($array === false) return [new Fixed(false)];
        if (is_string($array)) return [new RawSql($array)];
        if (!$array) return [];
        $filters = [];
        foreach ($array as $fullKey => $value){
            if (is_int($fullKey)){
                // If the array does not have string keys, the caller passed multiple filters arrays as an array of arrays
                if (!$value) continue; // skip nulls and empty arrays
                if ($value instanceof FiltersInterface) $filters[] = $value;
                elseif ($value instanceof FilterBuilder) $filters[] = $value->build_all();
                else $filters = array_merge($filters, static::parseArray($value));
                continue;
            }
            $keyParts = explode(' ', $fullKey);
            $fullCol = $keyParts[0];
            if ($fullKey[0] == '#') continue; // Subfilters. Ignore these; calling code should pull them out
            if ($fullKey[0] == '@'){
                // Not a column, a special directive
                switch ($fullCol){
                    case '@sql':
                        // a literal SQL snippet to be injected.
                        $filters[] = new RawSql($value);
                        break;
                    case '@params':
                        // Params for SQL snippet
                        // We add a a separate filters object because trying to match snippet to params is just not worth the added complexity.
                        $filters[] = new NullFilter($value);
                        break;
                    case '@(or)':
                    case '@or':
                        // Sub-filters to OR together
                        $filters[] = new OrGroup(static::parseArray($value));
                        break;
                    case '@(and)':
                    case '@and':
                    case '@()':
                        // Sub-filters to AND together
                        $filters[] = new AndGroup(static::parseArray($value));
                        break;
                }
                continue;
            }
            // Normal column-based key. Split to get the operator
            if (count($keyParts) == 1) $op = '=';
            else $op = $keyParts[1];
            // Split again if there is a table prefix
            list($table, $column) = splitColumn($fullCol);
            $filters[] = new Operator($table, $column, $op, $value);
        }
        return $filters;
    }

    /**
     * Build an SQL snippet to add to the WHERE clause which will apply the passed filters. The generated 
     * SQL will use keyword parameters (':' delimited) for parameterized queries. This method is useful
     * to add dynamic filters to a query.
     * 
     * The values of the passed filters array are the only thing escaped through parameterization; the
     * keys are not. It is assumed that the keys come from trusted code and not user inputs.
     * 
     * ```php
     * <?php
     * list($filterSnippet, $params) = Filters::toSql([
     *     'the_thing' => 'things',
     *     'value >' => 1,
     *     '@(or)' => ['blah LIKE%% thislabelisignored' => 'one thing', 'blah LIKE%% thisisignoredtoo' => 'the other thing']
     * ]);
     * 
     * // Example output (The unique random numbers at the end of the parameter name will differ)
     * $filterSnippet == "`the_thing` = :the_thing123454 AND `value` > :value246701 AND (`blah` LIKE CONCAT('%', :blah825972. '%') OR `blah` LIKE CONCAT('%', :blah651654. '%'))";
     * $params == ['the_thing123454' => 'things', 'value246701' => 1, 'blah825972' => 'one thing', 'blah651654' => 'the other thing'];
     * 
     * // So long as the *keys* of the filter array are trusted, you can safely use the generated snippet in your SQL.
     * $query = "SELECT * FROM some_table WHERE $filterSnippet";
     * $results = Data::executeParameterizedQuery($query, $params)->fetchAll(PDO::FETCH_OBJ);
     * ```
     * 
     * @param mixed[]|bool|null $filters An array in standard filters format.
     * @return array{string,array<string,mixed>} The first value is the SQL snippet, and the second is 
     * an array or parameter that is appropriate to pass to executeParameterizedQuery (after being merged
     * with your main parameter array of course)
     */
    public static function toSql($filters){
        return static::create($filters)->toSql();
    }

    /**
     * A special extended version of toSql that also supports subset labels in
     * the top-level filters array. This begin with a # sign in the key.
     * 
     *  @return array[] An array of two arrays. The first is an associative array mapping
     * subset labels to SQL snippets, and the second is the combined parameter array. for all
     * the snippets. Any filters that were passed without a subset label are under an
     * empty string key in the filters array. The empty string key is always present, even if 
     * is is empty. All others are only present if passed in the filters array.
     */
    public static function toMultipartSql($filters){
        $subsets = ['' => []];
        // Split out the subsets from the regular filters
        foreach ($filters as $key => $value){
            if (is_string($key) && $key[0] == '#') $subsets[$key] = $value;
            else $subsets[''][$key] = $value;
        }
        $snippets = [];
        $params = [];
        foreach ($subsets as $key => $value){
            list($snippet, $subParams) = static::toSql($value);
            $snippets[$key] = $snippet;
            $params = array_merge($params, $subParams);
        }
        return [$snippets, $params];
    }

    /**
     * Test if an object fits the criteria of a given set of filters
     * 
     * This only supports a subset of the filters available in toSql()
     * 
     * @param array|bool|null $object The data for the object to test
     * @param mixed|null $filters A filters array in the standard format
     * @return bool If the object matches the filters
     */
    public static function match($object, $filters){
        return static::create($filters)->match($object);
    }
}


/** Split a column name into table and column */
function splitColumn($key){
    $keyParts = explode('.', $key);
    $column = $keyParts[count($keyParts)-1];
    $table = count($keyParts) > 1 ? $keyParts[0] : null;
    return [$table, $column];
}
