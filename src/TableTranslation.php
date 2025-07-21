<?php
namespace DMJohnson\Miroql;

class TableTranslation{
    public function __construct(
        /** The alias that will be used to identify this table in the emitted SQL. This is also 
         * used as a unique key to identify this table within the miroql engine. 
         */
        public readonly string $alias,
        /** The real SQL table name used in the generated statement */
        public readonly string $table,
        /** @var ?array<string,JoinInfo> $joins A list of any joins needed to use this table, 
         * ultimately relative to the base table. The keys of this array are arbitrary, but should 
         * should be consistent, as they are used to avoid joining the same table multiple times. 
         */
        public readonly ?array $joins = null,
    ){}
}