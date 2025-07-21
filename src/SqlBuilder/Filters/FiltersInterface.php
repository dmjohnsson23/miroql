<?php
namespace DMJohnson\Miroql\SqlBuilder\Filters;

interface FiltersInterface{
    /** 
     * Convert the filters to an SQL snippet string.
     * 
     * @return array{?string,?array<string,mixed>} The SQL snippet built from these filters, 
     * and an array of parameters associated therewith.
     */
    function toSql(): array;
    /** 
     * Match the filters against an object.
     * 
     * @param array|\ArrayAccess $object The object to check
     * @return bool Whether or not the object matches these filters.
     */
    function match($object): bool;
    /** 
     * Convert the filters to array syntax for debuging/testing purposes
     * 
     * @return array
     */
    function toArraySyntax(): mixed;
}