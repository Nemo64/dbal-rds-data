<?php

namespace Nemo64\DbalRdsData\Tests;


use Aws\RDSDataService\RDSDataServiceClient;
use Aws\Result;
use Nemo64\DbalRdsData\RdsDataConnection;

trait RdsDataServiceClientTrait
{
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

    protected function createRdsDataServiceClient()
    {
        $this->client = $this->createMock(RDSDataServiceClient::class);
        $this->client->method('__call')->willReturnCallback(function ($methodName, $arguments) {
            $nextCall = array_shift($this->expectedCalls);
            $this->assertIsArray($nextCall, "there must be another call planned");
            $this->assertEquals($nextCall[0], $methodName, "method call");
            $this->assertEquals($nextCall[1], $arguments[0], "options of $methodName");
            return $nextCall[2];
        });

        $this->connection = new RdsDataConnection($this->client, 'arn:resource', 'arn:secret', 'db');
    }

    private function addClientCall(string $method, array $options, array $result)
    {
        $this->expectedCalls[] = [$method, $options, new Result($result)];
    }
}
