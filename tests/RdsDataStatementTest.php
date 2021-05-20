<?php

namespace Nemo64\DbalRdsData\Tests;


use Doctrine\DBAL\FetchMode;
use Nemo64\DbalRdsData\RdsDataStatement;
use PHPUnit\Framework\TestCase;

class RdsDataStatementTest extends TestCase
{
    use RdsDataServiceClientTrait;

    public function setUp(): void
    {
        $this->createRdsDataServiceClient();
    }

    public function testRetainFetchMode()
    {
        foreach (range(1, 2) as $item) {
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
                    'sql' => 'SELECT 1 AS id',
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
        }

        $statement = new RdsDataStatement($this->connection, 'SELECT 1 AS id');

        $statement->setFetchMode(FetchMode::NUMERIC);
        $statement->execute();
        $this->assertEquals([[1]], $statement->fetchAll());
        $statement->execute();
        $this->assertEquals([[1]], $statement->fetchAll());
    }
}
