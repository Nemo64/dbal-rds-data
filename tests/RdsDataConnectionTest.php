<?php

namespace Nemo64\DbalRdsData\Tests;


use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\FetchMode;
use Nemo64\DbalRdsData\RdsDataConnection;
use PHPUnit\Framework\TestCase;

class RdsDataConnectionTest extends TestCase
{
    private const DEFAULT_OPTIONS = [
        'resourceArn' => 'resource_arm',
        'secretArn' => 'secret_arm',
    ];

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|RDSDataServiceClient
     */
    private $client;

    private $expectedCalls = [
        // ['executeStatement', ['options' => 'value'], 'returnValue']
    ];

    /**
     * @var RdsDataConnection
     */
    private $connection;

    protected function setUp()
    {
        $this->client = $this->createMock(RDSDataServiceClient::class);
        $this->client->method('__call')->willReturnCallback(function ($methodName, $arguments) {
            $nextCall = array_shift($this->expectedCalls);
            $this->assertIsArray($nextCall, "there must be another call planned");
            $this->assertEquals($nextCall[0], $methodName, "method call");
            $this->assertEquals($nextCall[1], $arguments[0], "options of $methodName");
            return $nextCall[2];
        });

        $this->connection = new RdsDataConnection(
            $this->client,
            self::DEFAULT_OPTIONS['resourceArn'],
            self::DEFAULT_OPTIONS['secretArn'],
            'db'
        );
    }

    private function addClientCall(string $method, array $options, array $result)
    {
        $this->expectedCalls[] = [$method, $options, $result];
    }

    public function testSimpleQuery()
    {
        $this->addClientCall(
            'executeStatement',
            self::DEFAULT_OPTIONS + [
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
            self::DEFAULT_OPTIONS + ['database' => 'db'],
            ['transactionId' => '~~transaction id~~']
        );
        $this->assertTrue($this->connection->beginTransaction());
        $this->assertEquals('~~transaction id~~', $this->connection->getTransactionId());

        $this->addClientCall(
            'executeStatement',
            self::DEFAULT_OPTIONS + [
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
            self::DEFAULT_OPTIONS + ['transactionId' => '~~transaction id~~'],
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
            self::DEFAULT_OPTIONS + ['database' => 'db'],
            ['transactionId' => '~~transaction id~~']
        );
        $this->assertTrue($this->connection->beginTransaction());
        $this->assertEquals('~~transaction id~~', $this->connection->getTransactionId());
        $this->assertFalse($this->connection->beginTransaction());

        $this->addClientCall(
            'rollbackTransaction',
            self::DEFAULT_OPTIONS + ['transactionId' => '~~transaction id~~'],
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
            self::DEFAULT_OPTIONS + [
                'database' => 'db',
                'continueAfterTimeout' => false,
                'includeResultMetadata' => true,
                'parameters' => [],
                'resultSetOptions' => ['decimalReturnType' => 'STRING'],
                'sql' => 'UPDATE foobar SET value = 1',
            ],
            [
                'numberOfRecordsUpdated' => 5
            ]
        );

        $rowCount = $this->connection->exec('UPDATE foobar SET value = 1');
        $this->assertEquals(5, $rowCount);
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
}
