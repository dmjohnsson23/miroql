<?php
namespace DMJohnson\Miroql;

class JoinInfo{
    public function __construct(
        /** The real SQL name of the table to join */
        public readonly string $table,
        /** An SQL snippet used as the join condition */
        public readonly ?string $condition = null,
        /** @var null|'inner'|'left'|'right' $type The type of join (left, right, or inner) */
        public readonly ?string $type = null,
    ){}
}