<?php

namespace Nemo64\DbalRdsData\Tests;


use Nemo64\DbalRdsData\RdsDataParameterBag;
use PHPUnit\Framework\TestCase;

class RdsDataParameterBagTest extends TestCase
{
    public static function sqlPreparation()
    {
        return [
            [[0 => 1], 'SELECT * FROM x WHERE y = ?', 'SELECT * FROM x WHERE y = :0'],
            [[1 => 1], 'SELECT * FROM x WHERE y = ?', 'SELECT * FROM x WHERE y = :1'],
            [[1 => 1], "SELECT * FROM x WHERE x = '?' AND y = ?", "SELECT * FROM x WHERE x = '?' AND y = :1"],
            [[1 => 1], "SELECT * FROM x WHERE x = ? AND y = '?'", "SELECT * FROM x WHERE x = :1 AND y = '?'"],
            [[1 => 1], "SELECT * FROM x WHERE x = ? AND y = `?`", "SELECT * FROM x WHERE x = :1 AND y = `?`"],
            [[1 => 1], 'SELECT * FROM x WHERE x = ? AND y = "?"', 'SELECT * FROM x WHERE x = :1 AND y = "?"'],
            [['foo' => 1], 'SELECT * FROM x WHERE x = ? AND y = "?"', 'SELECT * FROM x WHERE x = ? AND y = "?"'],
        ];
    }

    /**
     * @dataProvider sqlPreparation
     */
    public function testPrepareSqlStatement(array $parameters, string $sql, string $expected)
    {
        $parameterBag = new RdsDataParameterBag();
        foreach ($parameters as $key => $value) {
            $parameterBag->bindValue($key, $value);
        }

        $this->assertEquals($expected, $parameterBag->prepareSqlStatement($sql));
    }

    public function testBind()
    {
        $parameterBag = new RdsDataParameterBag();

        $value = 'a';
        $parameterBag->bindParam('foo', $value);
        $parameterBag->bindValue('bar', $value);

        // change the parameter which should be "bound" too foo but not to bar
        $value = 'b';

        $finalParameters = $parameterBag->getParameters();

        $this->assertEquals('foo', $finalParameters[0]['name']);
        $this->assertEquals('b', $finalParameters[0]['value']['stringValue']);

        $this->assertEquals('bar', $finalParameters[1]['name']);
        $this->assertEquals('a', $finalParameters[1]['value']['stringValue']);
    }

    public function testBindDelete()
    {
        $parameterBag = new RdsDataParameterBag();

        $value = 'a';
        $parameterBag->bindParam('foo', $value);

        unset($value);

        $finalParameters = $parameterBag->getParameters();

        $this->assertEquals('foo', $finalParameters[0]['name']);
        $this->assertEquals('a', $finalParameters[0]['value']['stringValue']);
    }
}
