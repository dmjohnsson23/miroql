<?php
require_once __DIR__.'/../vendor/autoload.php';
use DMJohnson\Miroql\SqlBuilder\Filters;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Filters::class)]
class FiltersTest extends TestCase{
    public function testToSqlSimple(){
        list($sql, $params) = Filters::toSql(['vet_id'=>5]);
        $format = '/\(?`vet_id` = :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['vet_id'=>5, 'user_id'=>12]);
        $format = '/\(?`vet_id` = :([a-zA-Z0-9_]+) AND `user_id` = :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlWithDots(){
        list($sql, $params) = Filters::toSql(['claims.vet_id'=>5]);
        $format = '/\(?`claims`.`vet_id` = :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['claims.vet_id'=>5, 'claims.user_id'=>12]);
        $format = '/\(?`claims`.`vet_id` = :([a-zA-Z0-9_]+) AND `claims`.`user_id` = :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlWithOr(){
        list($sql, $params) = Filters::toSql(['@(or)'=>['claims.vet_id'=>5, 'claims.user_id'=>12]]);
        $format = '/\(?`claims`.`vet_id` = :([a-zA-Z0-9_]+) OR `claims`.`user_id` = :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlWithNestedAndOr(){
        list($sql, $params) = Filters::toSql(['@(or)'=>[
            '@(and) 1' => ['claims.vet_id'=>5, 'claims.user_id'=>12],
            '@(and) 2' => ['claims.vet_id'=>4, 'claims.user_id'=>14]
        ]]);
        $format = '/\(?\(`claims`.`vet_id` = :([a-zA-Z0-9_]+) AND `claims`.`user_id` = :([a-zA-Z0-9_]+)\) OR \(`claims`.`vet_id` = :([a-zA-Z0-9_]+) AND `claims`.`user_id` = :([a-zA-Z0-9_]+)\)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlWithInline(){
        list($sql, $params) = Filters::toSql(['@sql'=>"EXISTS (SELECT * FROM dependents WHERE vets.vets_id = dependents.vet_id)"]);
        $format = '/\(?EXISTS \(SELECT \* FROM dependents WHERE vets.vets_id = dependents.vet_id\)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlOperators(){
        list($sql, $params) = Filters::toSql(['vet_id >'=>5]);
        $format = '/\(?`vet_id` > :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['vet_id >='=>5]);
        $format = '/\(?`vet_id` >= :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['vet_id <'=>5]);
        $format = '/\(?`vet_id` < :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['vet_id <='=>5]);
        $format = '/\(?`vet_id` <= :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['vet_id !='=>5]);
        $format = '/\(?`vet_id` != :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlLike(){
        list($sql, $params) = Filters::toSql(['name LIKE'=>'something']);
        $format = '/\(?`name` LIKE :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['name NOT_LIKE'=>'something']);
        $format = '/\(?`name` NOT LIKE :([a-zA-Z0-9_]+)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['name LIKE%%'=>'something']);
        $format = "/\(?`name` LIKE CONCAT\('%', :([a-zA-Z0-9_]+), '%'\)\)?/";
        $this->assertMatchesRegularExpression($format, $sql);
        list($sql, $params) = Filters::toSql(['name NOT_LIKE%%'=>'something']);
        $format = "/\(?`name` NOT LIKE CONCAT\('%', :([a-zA-Z0-9_]+), '%'\)\)?/";
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlIn(){
        list($sql, $params) = Filters::toSql(['id IN'=> [1, 2, 3]]);
        $format = '/\(?`id` IN \(:([a-zA-Z0-9_]+), :([a-zA-Z0-9_]+), :([a-zA-Z0-9_]+)\)\)?/';
        $this->assertMatchesRegularExpression($format, $sql);
    }
    public function testToSqlSnippetWithParams(){
        $origParams = ['a'=>1, 'b'=>2];
        $origSql = 'a = :a AND b = :b';
        list($sql, $params) = Filters::toSql(['@sql'=>$origSql, '@params'=>$origParams]);
        $this->assertMatchesRegularExpression("/\(?$origSql\)?/", $sql);
        $this->assertEquals($origParams, $params);
    }
    public function testToSqlParamsOnly(){
        $origParams = ['a'=>1, 'b'=>2];
        list($sql, $params) = Filters::toSql(['@params'=>$origParams]);
        $this->assertNull($sql);
        $this->assertEquals($origParams, $params);
    }


    public function testMatchSimple(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters1 = ['vet_id'=>5];
        $filters2 = ['vet_id'=>5, 'user_id'=>12];
        $this->assertTrue(Filters::match($obj1, $filters1), 'Object 1 should match filters 1');
        $this->assertFalse(Filters::match($obj2, $filters1), 'Object 2 should not match filters 1');
        $this->assertTrue(Filters::match($obj3, $filters1), 'Object 3 should match filters 1');
        $this->assertFalse(Filters::match($obj4, $filters1), 'Object 4 should not match filters 1');
        $this->assertTrue(Filters::match($obj1, $filters2), 'Object 1 should match filters 2');
        $this->assertFalse(Filters::match($obj2, $filters2), 'Object 2 should not match filters 2');
        $this->assertFalse(Filters::match($obj3, $filters2), 'Object 3 should not match filters 2');
        $this->assertFalse(Filters::match($obj4, $filters2), 'Object 4 should not match filters 2');
    }
    public function testMatchWithDots(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters1 = ['claims.vet_id'=>5];
        $filters2 = ['claims.vet_id'=>5, 'claims.user_id'=>12];
        $this->assertTrue(Filters::match($obj1, $filters1), 'Object 1 should match filters 1');
        $this->assertFalse(Filters::match($obj2, $filters1), 'Object 2 should not match filters 1');
        $this->assertTrue(Filters::match($obj3, $filters1), 'Object 3 should match filters 1');
        $this->assertFalse(Filters::match($obj4, $filters1), 'Object 4 should not match filters 1');
        $this->assertTrue(Filters::match($obj1, $filters2), 'Object 1 should match filters 2');
        $this->assertFalse(Filters::match($obj2, $filters2), 'Object 2 should not match filters 2');
        $this->assertFalse(Filters::match($obj3, $filters2), 'Object 3 should not match filters 2');
        $this->assertFalse(Filters::match($obj4, $filters2), 'Object 4 should not match filters 2');
    }
    public function testMatchWithOr(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters = ['@(or)'=>['claims.vet_id'=>5, 'claims.user_id'=>12]];
        $this->assertTrue(Filters::match($obj1, $filters), 'Object 1 should match filters');
        $this->assertTrue(Filters::match($obj2, $filters), 'Object 2 should match filters');
        $this->assertTrue(Filters::match($obj3, $filters), 'Object 3 should match filters');
        $this->assertFalse(Filters::match($obj4, $filters), 'Object 4 should not match filters');
    }
    public function testMatchWithNestedAndOr(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $obj5 = ['vet_id'=>4, 'user_id'=>14];
        $filters = ['@(or)'=>[
            '@(and) 1' => ['claims.vet_id'=>5, 'claims.user_id'=>12],
            '@(and) 2' => ['claims.vet_id'=>4, 'claims.user_id'=>14]
        ]];
        $this->assertTrue(Filters::match($obj1, $filters), 'Object 1 should match filters');
        $this->assertFalse(Filters::match($obj2, $filters), 'Object 2 should not match filters');
        $this->assertFalse(Filters::match($obj3, $filters), 'Object 3 should not match filters');
        $this->assertFalse(Filters::match($obj4, $filters), 'Object 4 should not match filters');
        $this->assertTrue(Filters::match($obj5, $filters), 'Object 5 should match filters');
    }
    public function testMatchGreaterThan(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters = ['vet_id >'=>5];
        $this->assertFalse(Filters::match($obj1, $filters), 'Object 1 should not match filters');
        $this->assertTrue(Filters::match($obj2, $filters), 'Object 2 should match filters');
        $this->assertFalse(Filters::match($obj3, $filters), 'Object 3 should not match filters');
        $this->assertFalse(Filters::match($obj4, $filters), 'Object 4 should not match filters');
    }
    public function testMatchGreaterThanEqual(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters = ['vet_id >='=>5];
        $this->assertTrue(Filters::match($obj1, $filters), 'Object 1 should match filters');
        $this->assertTrue(Filters::match($obj2, $filters), 'Object 2 should match filters');
        $this->assertTrue(Filters::match($obj3, $filters), 'Object 3 should match filters');
        $this->assertFalse(Filters::match($obj4, $filters), 'Object 4 should not match filters');
    }
    public function testMatchLessThan(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters = ['vet_id <'=>5];
        $this->assertFalse(Filters::match($obj1, $filters), 'Object 1 should not match filters');
        $this->assertFalse(Filters::match($obj2, $filters), 'Object 2 should not match filters');
        $this->assertFalse(Filters::match($obj3, $filters), 'Object 3 should not match filters');
        $this->assertTrue(Filters::match($obj4, $filters), 'Object 4 should match filters');
    }
    public function testMatchLessThanEqual(){
        $obj1 = ['vet_id'=>5, 'user_id'=>12];
        $obj2 = ['vet_id'=>6, 'user_id'=>12];
        $obj3 = ['vet_id'=>5, 'user_id'=>14];
        $obj4 = ['vet_id'=>3, 'user_id'=>14];
        $filters = ['vet_id <='=>5];
        $this->assertTrue(Filters::match($obj1, $filters), 'Object 1 should match filters');
        $this->assertFalse(Filters::match($obj2, $filters), 'Object 2 should not match filters');
        $this->assertTrue(Filters::match($obj3, $filters), 'Object 3 should match filters');
        $this->assertTrue(Filters::match($obj4, $filters), 'Object 4 should match filters');
    }
    public function testMatchesLike(){
        $this->assertTrue(Filters::match(['name'=>"Well, ain't that something"], ['name LIKE'=>'something']), 'Object 1 should match');
        $this->assertTrue(Filters::match(['name'=>"There's nothing quite like it"], ['name NOT_LIKE'=>'something']), 'Object 2 should match');
        $this->assertTrue(Filters::match(['name'=>"Things always happen"], ['name LIKE%%'=>'thing']), 'Object 3 should match');
        $this->assertTrue(Filters::match(['name'=>"Whether you want them to happen or not...."], ['name NOT_LIKE%%'=>'thing']), 'Object 4 should match');
        // Wow guys, I think wrote a song...
    }
    public function testMatchesIn(){
        $filters = ['id IN'=> [1, 2, 3]];
        $this->assertTrue(Filters::match(['id'=>1], $filters), 'Object 1 should match filters');
        $this->assertTrue(Filters::match(['id'=>2], $filters), 'Object 2 should match filters');
        $this->assertTrue(Filters::match(['id'=>3], $filters), 'Object 3 should match filters');
        $this->assertFalse(Filters::match(['id'=>4], $filters), 'Object 4 should not match filters');
    }
}