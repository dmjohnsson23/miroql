<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

use \DMJohnson\Miroql\SqlBuilder\SQlBuilderException;

/**
 * Represents a basic binary operator comparing a column to a specific value.
 */
class Operator implements FiltersInterface{
    protected $table;
    protected $column;
    protected $operator;
    protected $value;

    /**
     * @param literal-string|null $table The name of the table this filters applies to (if applicable)
     * @param literal-string $column The column to filter by
     * @param '='|'!='|'>'|'<'|'>='|'<='|'LIKE'|'LIKE%%'|'NOT_LIKE'|'NOT_LIKE%%'|'IN'|'NOT_IN'|'REGEXP' $operator The operator to use when filtering
     */
    public function __construct($table, $column, $operator, $value){
        $this->table = $table;
        $this->column = $column;
        $this->operator = $operator;
        $this->value = $value;
    }

    public function toSql(): array{
        $snippet = '1';
        $params = [];
        $paramsKey = uniqid($this->column);
        $quotedKey = "`$this->column`";
        if (isset($this->table)){
            $quotedKey = "`$this->table`.$quotedKey";
        }
        switch ($this->operator){
            case '=':
            case '!=':
                if (is_null($this->value)){
                    // Special case for null
                    if ($this->operator == '=') $snippet = "$quotedKey IS NULL";
                    else $snippet = "$quotedKey IS NOT NULL";
                    break; // skip adding the param
                }
                // deliberate fallthrough for non-null values
            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'LIKE':
            case 'REGEXP':
                $snippet = "$quotedKey $this->operator :$paramsKey";
                $params[$paramsKey] = $this->value;
                break;
            case 'LIKE%%':
                $snippet = "$quotedKey LIKE CONCAT('%', :$paramsKey, '%')";
                $params[$paramsKey] = $this->value;
                break;
            case 'NOT_LIKE':
                $snippet = "$quotedKey NOT LIKE :$paramsKey";
                $params[$paramsKey] = $this->value;
                break;
            case 'NOT_LIKE%%':
                $snippet = "$quotedKey NOT LIKE CONCAT('%', :$paramsKey, '%')";
                $params[$paramsKey] = $this->value;
                break;
            case 'NOT_REGEXP':
                $snippet = "$quotedKey NOT REGEXP :$paramsKey";
                $params[$paramsKey] = $this->value;
                break;
            case 'BETWEEN':
                $snippet = "$quotedKey BETWEEN :min$paramsKey AND :max$paramsKey)";
                $params["min$paramsKey"] = $this->value[0];
                $params["max$paramsKey"] = $this->value[1];
                break;
            case 'IN':
                $keys = [];
                if (empty($this->value)){
                    $snippet = '0'; // Empty array (or null) was passed, treat as false because a value can never be in an empty array
                    break;
                }
                foreach (array_values($this->value) as $arrIndex => $arrValue){
                    $keys[] = ":$paramsKey$arrIndex";
                    $params["$paramsKey$arrIndex"] = $arrValue;
                }
                $snippet = "$quotedKey IN (".implode(', ', $keys).')';
                break;
            case 'NOT_IN':
                $keys = [];
                foreach (array_values($this->value) as $arrIndex => $arrValue){
                    $keys[] = ":$paramsKey$arrIndex";
                    $params["$paramsKey$arrIndex"] = $arrValue;
                }
                if($keys) $snippet = "$quotedKey NOT IN (".implode(', ', $keys).')';
                else $snippet = '1'; // Empty array was passed, treat as true because a value can never be in an empty array
                break;
            default:
                throw new SQlBuilderException("Could not build query with unknown filter operator '$this->operator'");  
        }
        return [$snippet, $params];
    }

    public function match($object): bool{
        $objectValue = $object[$this->column];
        switch ($this->operator){
            case '=':
                if (is_null($this->value)){
                    // Special case for null
                    return is_null($objectValue);
                }
                else{
                    return $objectValue == $this->value;
                }
            case '!=':
                if (is_null($this->value)){
                    // Special case for null
                    return !is_null($objectValue);
                }
                else{
                    return $objectValue != $this->value;
                }
            case '>':
                return $objectValue > $this->value;
            case '<':
                return $objectValue < $this->value;
            case '>=':
                return $objectValue >= $this->value;
            case '<=';
                return $objectValue <= $this->value;
            case 'LIKE':
                return like($this->value, $objectValue);
            case 'LIKE%%':
                return like("%$this->value%", $objectValue);
            case 'NOT_LIKE':
                return !like($this->value, $objectValue);
            case 'NOT_LIKE%%':
                return !like("%$this->value%", $objectValue);
            case 'IN':
                return in_array($objectValue, $this->value);
            case 'NOT_IN':
                return !in_array($objectValue, $this->value);
            default:
                throw new SQlBuilderException("Could not build query with unknown filter operator '$this->operator'");  
        }
    }

    public function toArraySyntax(): array{
        return ["$this->table.$this->column $this->operator" => $this->value];
    }
}

/**
 * Convert a pattern in MySQL's LIKE syntax to a regular expression pattern
 */
function likeToRegex($likePattern, $escapeCharacter=null){
    $isEscaped=false;
    $pattern = '';
    foreach (str_split($likePattern) as $char){
        if ($char == '%' && !$isEscaped) $pattern .= '.*';
        elseif ($char == '_' && !$isEscaped) $pattern .= '.';
        elseif ($char == $escapeCharacter && !$isEscaped) $isEscaped = true;
        else{

            $pattern .= preg_quote($char, '/');
            $isEscaped = false;
        }
    }
    return "/$pattern/i";
}

function like($likePattern, $matchAgainst, $escapeCharacter=null){
    $match = preg_match(likeToRegex($likePattern, $escapeCharacter), $matchAgainst);
    if ($match === false) throw new \RuntimeException(preg_last_error_msg());
    return boolval($match);
}