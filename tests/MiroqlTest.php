<?php
require_once __DIR__.'/../vendor/autoload.php';
use DMJohnson\Miroql\SqlBuilder\Filters;
use DMJohnson\Miroql\Miroql;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

Filters::create(true); // To trigger the file to be autoincluded

#[CoversClass(Miroql::class)]
#[UsesClass(Filters::class)]
class MiroqlTest extends TestCase{
    public function testMakeFiltersExplicitEverything(){
        $miroql = new Miroql();
        $selector = [
            '$and'=>[
                ['veteran.f_name' => ['$eq'=>'Charlie']],
                ['veteran.l_name' => ['$eq'=>'Smith']]
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["veteran.f_name =" => "Charlie"],
                    ["veteran.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersWithNot(){
        $miroql = new Miroql();
        $selector = [
            '$and'=>[
                ['veteran.f_name' => ['$not'=>['$eq'=>'Charlie']]],
                ['veteran.l_name' => ['$not'=>['$eq'=>'Smith']]]
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ['@not'=>["veteran.f_name =" => "Charlie"]],
                    ['@not'=>["veteran.l_name =" => "Smith"]],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersImplicitTable(){
        $miroql = new Miroql();
        $selector = [
            '$and'=>[
                ['f_name' => ['$eq'=>'Charlie']],
                ['l_name' => ['$eq'=>'Smith']]
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["veteran.f_name =" => "Charlie"],
                    ["veteran.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersImplicitGroup(){
        $miroql = new Miroql();
        $selector = [
            'veteran.f_name' => ['$eq'=>'Charlie'],
            'veteran.l_name' => ['$eq'=>'Smith']
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["veteran.f_name =" => "Charlie"],
                    ["veteran.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersImplicitOperator(){
        $miroql = new Miroql();
        $selector = [
            '$and'=>[
                ['veteran.f_name' => 'Charlie'],
                ['veteran.l_name' => 'Smith']
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["veteran.f_name =" => "Charlie"],
                    ["veteran.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersImplicitEverything(){
        $miroql = new Miroql();
        $selector = [
            'f_name' => 'Charlie',
            'l_name' => 'Smith'
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["veteran.f_name =" => "Charlie"],
                    ["veteran.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersNestedFieldsImplicitOperator(){
        $miroql = new Miroql();
        $selector = [
            'user' => [
                'f_name' => 'Charlie',
                'l_name' => 'Smith'
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["user.f_name =" => "Charlie"],
                    ["user.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeFiltersNestedFieldsExplicitOperator(){
        $miroql = new Miroql();
        $selector = [
            'user' => [
                'f_name' => ['$eq' =>'Charlie'],
                'l_name' => ['$eq' =>'Smith']
            ]
        ];
        $filters = $miroql->makeFilters($selector, 'veteran')->build()->toArraySyntax();
        $this->assertEquals(
            [
                "@and" => [
                    ["user.f_name =" => "Charlie"],
                    ["user.l_name =" => "Smith"],
                ]
            ],
            $filters
        );
    }
    public function testMakeSelectedColumnsExplicitEverything(){
        $miroql = new Miroql();
        $fields = [
            ['$value' => 'user.f_name'],
            ['$value' => 'user.l_name'],
        ];
        $columns = $miroql->makeSelectedColumns($fields, 'veteran');
        $this->assertEquals(
            [
                '$value.user.f_name'=>'user.f_name',
                '$value.user.l_name'=>'user.l_name',
            ],
            $columns
        );
    }
    public function testMakeSelectedColumnsWithAggregate(){
        $miroql = new Miroql();
        $fields = [
            ['$max' => 'veteran.dob'],
        ];
        $columns = $miroql->makeSelectedColumns($fields, 'veteran');
        $this->assertEquals(
            [
                '$max.veteran.dob'=>'MAX(veteran.dob)',
            ],
            $columns
        );
    }
    public function testMakeSelectedColumnsImplicitAggregate(){
        $miroql = new Miroql();
        $fields = [
            'user.f_name',
            'user.l_name',
        ];
        $columns = $miroql->makeSelectedColumns($fields, 'veteran');
        $this->assertEquals(
            [
                'user.f_name'=>'user.f_name',
                'user.l_name'=>'user.l_name',
            ],
            $columns
        );
    }
    public function testMakeSelectedColumnsImplicitTable(){
        $miroql = new Miroql();
        $fields = [
            ['$value' => 'f_name'],
            ['$value' => 'l_name'],
        ];
        $columns = $miroql->makeSelectedColumns($fields, 'veteran');
        $this->assertEquals(
            [
                '$value.f_name'=>'veteran.f_name',
                '$value.l_name'=>'veteran.l_name',
            ],
            $columns
        );
    }
    public function testMakeSelectedColumnsImplicitEverything(){
        $miroql = new Miroql();
        $fields = [
            'f_name',
            'l_name',
        ];
        $columns = $miroql->makeSelectedColumns($fields, 'veteran');
        $this->assertEquals(
            [
                'f_name'=>'veteran.f_name',
                'l_name'=>'veteran.l_name',
            ],
            $columns
        );
    }
    public function testMakeOrderByListWithImplicitDirection(){
        $miroql = new Miroql();
        $sort = [
            'f_name',
            'l_name',
        ];
        $orderBy = $miroql->makeOrderBy($sort, 'veteran');
        $this->assertEquals(
            'veteran.f_name, veteran.l_name',
            $orderBy
        );
    }
    public function testMakeOrderByListWithExplicitDirection(){
        $miroql = new Miroql();
        $sort = [
            ['f_name'=>'desc'],
            ['l_name'=>'asc'],
        ];
        $orderBy = $miroql->makeOrderBy($sort, 'veteran');
        $this->assertEquals(
            'veteran.f_name DESC, veteran.l_name ASC',
            $orderBy
        );
    }
    public function testMakeOrderBySingleWithExplicitDirection(){
        $miroql = new Miroql();
        $sort = ['l_name'=>'asc'];
        $orderBy = $miroql->makeOrderBy($sort, 'veteran');
        $this->assertEquals(
            'veteran.l_name ASC',
            $orderBy
        );
    }
    public function testMakeOrderBySingleWithImplicitDirection(){
        $miroql = new Miroql();
        $sort = 'l_name';
        $orderBy = $miroql->makeOrderBy($sort, 'veteran');
        $this->assertEquals(
            'veteran.l_name',
            $orderBy
        );
    }
    public function testMakeGroupByList(){
        $miroql = new Miroql();
        $group = [
            'f_name',
            'l_name',
        ];
        $groupBy = $miroql->makeGroupBy($group, 'veteran');
        $this->assertEquals(
            'veteran.f_name, veteran.l_name',
            $groupBy
        );
    }
    public function testMakeGroupBySingle(){
        $miroql = new Miroql();
        $group = 'l_name';
        $groupBy = $miroql->makeGroupBy($group, 'veteran');
        $this->assertEquals(
            'veteran.l_name',
            $groupBy
        );
    }
    public function testJoins(){
        $miroql = new Miroql();
        $params=[];
        $query = $miroql->makeQuery(['fields'=>['cvso.f_name']], 'veteran')->build($params);
        $this->assertEquals(
            'SELECT `cvso`.`f_name` AS `cvso.f_name` FROM veteran AS `veteran` JOIN cvso AS `cvso` ;',
            $query
        );
    }
    public function testJoinsLeft(){
        $miroql = new Miroql();
        $params=[];
        $query = $miroql->makeQuery(['fields'=>['cvso.f_name'], 'join'=>'left'], 'veteran')->build($params);
        $this->assertEquals(
            'SELECT `cvso`.`f_name` AS `cvso.f_name` FROM veteran AS `veteran` LEFT JOIN cvso AS `cvso` ;',
            $query
        );
    }
}