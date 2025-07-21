<?php
namespace DMJohnson\Miroql;

class ColumnTranslation{
    public function __construct(
        /** The alias for this column in the emitted statement */
        public readonly string $alias,
        /** An SQL snipped used in the SELECT clause of the emitted statement */
        public readonly string $selector,
        /** The table this column belongs to */
        public readonly TableTranslation $table,
    ){}
}