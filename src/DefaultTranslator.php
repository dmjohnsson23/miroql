<?php
namespace DMJohnson\Miroql;

/**
 * Simple default name translator, mostly used for testing. 
 * 
 * It is recommended that a custom name translator be used. This one makes several (incorrect) 
 * assumptions:
 * 
 *  - Any syntacticlly correct keys are valid
 *  - No join conditions are needed
 *  - The public naming convention exactly matches the database schema
 */
class DefaultTranslator implements Translator{
    function bodyFieldName(string $alias, string $baseTable): ?ColumnTranslation{
        if (!\preg_match('/^(?:([a-zA-Z_]+)\.)?([a-zA-Z_]+)$/', $alias, $match)){
            return null;
        }
        $table = $match[1] ?: $baseTable;
        return new ColumnTranslation(
            $alias,
            $alias,
            new TableTranslation(
                $table,
                $table,
                $table===$baseTable ? [] : [
                    $table => new JoinInfo($table),
                ]
            )
        );
    }

    function baseTableName(string $alias): ?TableTranslation{
        if (!\preg_match('/^[a-zA-Z_]+$/', $alias, $match)){
            return null;
        }
        return new TableTranslation(
            $alias,
            $alias,
        );
    }

    function getFilters(array $tables): ?SqlBuilder\Filters\FiltersInterface{
        return null;
    }
}