<?php

namespace Nemo64\DbalRdsData\Tests;


use Aws\Result;
use Doctrine\DBAL\FetchMode;
use Nemo64\DbalRdsData\RdsDataResult;
use Nemo64\DbalRdsData\Tests\TestClasses\ClassWithConstructor;
use PHPUnit\Framework\TestCase;

class RdsDataResultTest extends TestCase
{
    /**
     * @var RdsDataResult
     */
    private $result;

    public static function modes()
    {
        yield 'ASSOCIATIVE' => [
            [FetchMode::ASSOCIATIVE],
            ['col1' => 'foo', 'col2' => 'bar'],
        ];

        yield 'NUMERIC' => [
            [FetchMode::NUMERIC],
            ['foo', 'bar'],
        ];

        yield 'MIXED' => [
            [FetchMode::MIXED],
            ['col1' => 'foo', 'col2' => 'bar', 'foo', 'bar'],
        ];

        yield 'STANDARD_OBJECT' => [
            [FetchMode::STANDARD_OBJECT],
            (object)['col1' => 'foo', 'col2' => 'bar'],
        ];

        yield 'COLUMN' => [
            [FetchMode::COLUMN],
            'foo',
        ];

        yield 'COLUMN_SPECIFIC' => [
            [FetchMode::COLUMN, 1],
            'bar',
        ];

        yield 'CUSTOM_OBJECT' => [
            [FetchMode::CUSTOM_OBJECT, \stdClass::class],
            (object)['col1' => 'foo', 'col2' => 'bar'],
        ];

        $object = new ClassWithConstructor();
        $object->set('foo', 'bar');
        $object->dataDuringConstruct = ['foo', 'bar'];
        yield 'CUSTOM_OBJECT_WITH_PROPER_CLASS' => [
            [FetchMode::CUSTOM_OBJECT, ClassWithConstructor::class], $object
        ];

        $object = clone $object;
        $object->dataPassedToConstructor = ['hi'];
        yield 'CUSTOM_OBJECT_WITH_PROPER_CLASS_AND_CONSTRUCTOR_ARGUMENT' => [
            [FetchMode::CUSTOM_OBJECT, ClassWithConstructor::class, 'hi'], $object
        ];

        $object = clone $object;
        $object->dataPassedToConstructor = ['arg1', 'arg2'];
        yield 'CUSTOM_OBJECT_WITH_PROPER_CLASS_AND_CONSTRUCTOR_ARGUMENT2' => [
            [FetchMode::CUSTOM_OBJECT, ClassWithConstructor::class, ['arg1', 'arg2']], $object
        ];
    }

    protected function setUp(): void
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
    public function testFetch($fetchMode, $expectedResult)
    {
        $this->result->setFetchMode(...$fetchMode);

        $this->assertEquals($expectedResult, $this->result->fetch());
        $this->assertEquals(false, $this->result->fetch());
    }

    /**
     * @dataProvider modes
     */
    public function testFetchAllSetFetchMode($fetchMode, $expectedResult)
    {
        $this->result->setFetchMode(...$fetchMode);
        $this->assertEquals(
            [$expectedResult],
            $this->result->fetchAll()
        );
    }

    /**
     * @dataProvider modes
     */
    public function testFetchAllGiveFetchMode($fetchMode, $expectedResult)
    {
        $this->assertEquals(
            [$expectedResult],
            $this->result->fetchAll(...$fetchMode)
        );
    }

    public function testFetchColumn()
    {
        $this->assertEquals('bar', $this->result->fetchColumn(1));
        $this->assertEquals(false, $this->result->fetchColumn(1));
    }

    /**
     * @dataProvider modes
     */
    public function testIterator($fetchMode, $expectedResult)
    {
        $this->result->setFetchMode(...$fetchMode);
        $this->assertEquals([$expectedResult], iterator_to_array($this->result->getIterator()));
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
