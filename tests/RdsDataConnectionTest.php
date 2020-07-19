<?php

namespace Nemo64\DbalRdsData\Tests;


use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;

class RdsDataConnectionTest extends TestCase
{
    use RdsDataServiceClientTrait;

    protected function setUp()
    {
        $this->createRdsDataServiceClient();
    }

    public function testSimpleQuery()
    {
        $this->addClientCall(
            'executeStatement',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'SELECT * FROM table',
            ],
            [
                "columnMetadata" => [
                    ["label" => "id"],
                ],
                "numberOfRecordsUpdated" => 0,
                "records" => [
                    [
                        ["longValue" => 1],
                    ],
                ],
            ]
        );

        $statement = $this->connection->query('SELECT * FROM table');
        $this->assertEquals(['id' => 1], $statement->fetch(FetchMode::ASSOCIATIVE));
        $this->assertEquals(false, $statement->fetch(FetchMode::ASSOCIATIVE));
    }

    public function testTransaction()
    {
        $this->addClientCall(
            'beginTransaction',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
            ],
            ['transactionId' => '~~transaction id~~']
        );
        $this->assertTrue($this->connection->beginTransaction());
        $this->assertEquals('~~transaction id~~', $this->connection->getTransactionId());

        $this->addClientCall(
            'executeStatement',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'SELECT * FROM table',
                'transactionId' => $this->connection->getTransactionId(),
            ],
            [
                "columnMetadata" => [
                    ["label" => "id"],
                ],
                "numberOfRecordsUpdated" => 0,
                "records" => [
                    [
                        ["longValue" => 1],
                    ],
                ],
            ]
        );
        $statement = $this->connection->query('SELECT * FROM table');
        $this->assertEquals(['id' => 1], $statement->fetch(FetchMode::ASSOCIATIVE));
        $this->assertEquals(false, $statement->fetch(FetchMode::ASSOCIATIVE));

        $this->assertFalse($this->connection->beginTransaction());

        $this->addClientCall(
            'commitTransaction',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'transactionId' => '~~transaction id~~',
            ],
            ['transactionStatus' => 'cleaning up']
        );
        $this->assertTrue($this->connection->commit());
        $this->assertFalse($this->connection->commit());
        $this->assertFalse($this->connection->rollBack());
    }

    public function testRollBack()
    {
        $this->addClientCall(
            'beginTransaction',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
            ],
            ['transactionId' => '~~transaction id~~']
        );
        $this->assertTrue($this->connection->beginTransaction());
        $this->assertEquals('~~transaction id~~', $this->connection->getTransactionId());
        $this->assertFalse($this->connection->beginTransaction());

        $this->addClientCall(
            'rollbackTransaction',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'transactionId' => '~~transaction id~~',
            ],
            ['transactionStatus' => 'cleaning up']
        );
        $this->assertTrue($this->connection->rollBack());
        $this->assertFalse($this->connection->rollBack());
        $this->assertFalse($this->connection->commit());
    }

    public function testUpdate()
    {
        $this->addClientCall(
            'executeStatement',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'UPDATE foobar SET value = 1',
            ],
            [
                'numberOfRecordsUpdated' => 5,
            ]
        );

        $rowCount = $this->connection->exec('UPDATE foobar SET value = 1');
        $this->assertEquals(5, $rowCount);
    }

    public function testParameters()
    {
        $this->addClientCall(
            'executeStatement',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [
                    ['name' => '0', 'value' => ['stringValue' => 5]],
                ],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'UPDATE foobar SET value = :0',
            ],
            [
                'numberOfRecordsUpdated' => 5,
            ]
        );

        $statement = $this->connection->prepare('UPDATE foobar SET value = ?');
        $statement->bindValue(0, 5);
        $statement->execute();
        $this->assertEquals(5, $statement->rowCount());
    }

    public static function quoteValues()
    {
        return [
            ['foobar', "'foobar'"],
            ['foo\'bar', "'foo\\'bar'"],
            ['äöü', sprintf("FROM_BASE64('%s')", base64_encode('äöü'))],
        ];
    }

    /**
     * @dataProvider quoteValues
     */
    public function testQuote($value, $expectation)
    {
        $this->assertEquals($expectation, $this->connection->quote($value));
    }

    public function testInsert()
    {
        $this->addClientCall(
            'executeStatement',
            [
                'resourceArn' => 'arn:resource',
                'secretArn' => 'arn:secret',
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [
                    ['name' => '0', 'value' => ['stringValue' => 5]],
                ],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'INSERT INTO foobar SET value = :0',
            ],
            [
                'numberOfRecordsUpdated' => 1,
                'generatedFields' => [
                    ['longValue' => 5]
                ]
            ]
        );

        $statement = $this->connection->prepare('INSERT INTO foobar SET value = ?');
        $statement->execute([5]);
        $this->assertEquals(1, $statement->rowCount());
        $this->assertEquals(5, $this->connection->lastInsertId());
    }

    public static function databaseUseStatements()
    {
        return [
            ['foobar', 'use foobar'],
            ['foobar', 'use foobar;'],
            ['foobar', 'use   foobar ; '],
            ['bar foo', 'use `bar foo`'],
            ['bar foo', 'use `bar foo`;'],
            ['bar foo', 'use   `bar foo`  ;  '],
        ];
    }

    /**
     * @dataProvider databaseUseStatements
     */
    public function testUseDatabase($dbname, $useStatement)
    {
        $this->assertEquals('db', $this->connection->getDatabase());
        $statement = $this->connection->prepare($useStatement);
        $this->assertEquals('db', $this->connection->getDatabase());
        $this->assertTrue($statement->execute());
        $this->assertEquals($dbname, $this->connection->getDatabase());
    }
}
