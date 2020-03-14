<?php

namespace Nemo64\DbalRdsData\Tests;


use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\FetchMode;
use Nemo64\DbalRdsData\RdsDataConnection;
use PHPUnit\Framework\TestCase;

class RdsDataConnectionTest extends TestCase
{
    public function testSimpleQuery()
    {
        $client = $this->createMock(RDSDataServiceClient::class);
        $client->expects($this->once())
            ->method('__call')
            ->with('executeStatement', [[
                'continueAfterTimeout' => false,
                'database' => 'db',
                'includeResultMetadata' => true,
                'parameters' => [],
                'resourceArn' => 'resource_arm',
                'resultSetOptions' => [
                    'decimalReturnType' => 'STRING',
                ],
                'secretArn' => 'secret_arm',
                'sql' => 'SELECT * FROM table',
            ]])
            ->willReturn([
                "columnMetadata" => [
                    ["label" => "id"],
                ],
                "numberOfRecordsUpdated" => 0,
                "records" => [
                    [
                        ["longValue" => 1],
                    ],
                ],
            ]);
        $connection = new RdsDataConnection($client, 'resource_arm', 'secret_arm', 'db');
        $statement = $connection->query('SELECT * FROM table');
        $this->assertEquals(['id' => 1], $statement->fetch(FetchMode::ASSOCIATIVE));
        $this->assertEquals(false, $statement->fetch(FetchMode::ASSOCIATIVE));
    }
}
