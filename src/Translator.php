<?php
namespace DMJohnson\Miroql;

interface Translator{
    /**
     * Translate a field as taken from the body of query.
     * 
     * @param string $alias The name to translate; also used as the alias for this field in the emitted SQL
     * @param string $baseTable The (untranslated) name of the base table the query is running against
     * @return ?ColumnTranslation
     */
    function bodyFieldName(string $alias, string $baseTable): ?ColumnTranslation;

    /**
     * Translate the name of the base table.
     * 
     * @param string $alias The name to translate; also used as the alias for this table in the emitted SQL
     * @return ?TableTranslation
     */
    function baseTableName(string $alias): ?TableTranslation;

    /**
     * Given a list of tables, build any filters which may be nessesary to add to the emiitted SQL.
     * 
     * This can be used to:
     * 
     * - Apply limitations based on user permissions.
     * - Filter out hidden or otherwise irrelevent rows from tables.
     * 
     * @param TableTranslation[] $tables
     */
    function getFilters(array $tables): ?SqlBuilder\Filters\FiltersInterface;
}