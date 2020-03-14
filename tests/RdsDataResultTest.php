<?php

namespace Nemo64\DbalRdsData\Tests;


use Aws\Result;
use Doctrine\DBAL\FetchMode;
use Nemo64\DbalRdsData\RdsDataResult;
use PHPUnit\Framework\TestCase;

class RdsDataResultTest extends TestCase
{
    /**
     * @var RdsDataResult
     */
    private $result;

    public static function modes()
    {
        return [
            [
                FetchMode::ASSOCIATIVE,
                [
                    ['col1' => 'foo', 'col2' => 'bar'],
                ],
            ],
            [
                FetchMode::NUMERIC,
                [
                    ['foo', 'bar'],
                ],
            ],
            [
                FetchMode::MIXED,
                [
                    ['col1' => 'foo', 'col2' => 'bar', 'foo', 'bar'],
                ],
            ],
            [
                FetchMode::STANDARD_OBJECT,
                [
                    (object)['col1' => 'foo', 'col2' => 'bar'],
                ],
            ],
            [
                FetchMode::COLUMN,
                [
                    'foo',
                ],
            ],
        ];
    }

    protected function setUp()
    {
        $this->result = new RdsDataResult(new Result([
            'columnMetadata' => [
                ['label' => 'col1'],
                ['label' => 'col2'],
            ],
            'records' => [
                [
                    ['stringValue' => 'foo'],
                    ['stringValue' => 'bar'],
                ],
            ],
        ]));
    }

    /**
     * @dataProvider modes
     */
    public function testFetch($fetchMode, $expectedResults)
    {
        $this->result->setFetchMode($fetchMode);

        foreach ($expectedResults as $expectedResult) {
            $this->assertEquals($expectedResult, $this->result->fetch());
        }

        $this->assertEquals(false, $this->result->fetch());
    }

    /**
     * @dataProvider modes
     */
    public function testFetchAll($fetchMode, $expectedResults)
    {
        $this->result->setFetchMode($fetchMode);
        $this->assertEquals($expectedResults, $this->result->fetchAll());
    }

    public function testFetchColumn()
    {
        $this->assertEquals('foo', $this->result->fetchColumn());
        $this->assertEquals(false, $this->result->fetchColumn());
    }

    /**
     * @dataProvider modes
     */
    public function testIterator($fetchMode, $expectedResults)
    {
        $this->result->setFetchMode($fetchMode);
        $this->assertEquals($expectedResults, iterator_to_array($this->result->getIterator()));
    }

    public function testColumnCount()
    {
        $this->assertEquals(2, $this->result->columnCount());
    }

    public function testCount()
    {
        $this->assertEquals(1, $this->result->rowCount());
    }
}
