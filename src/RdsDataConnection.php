<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\Driver\Statement;

class RdsDataConnection extends AbstractConnection
{
    /**
     * @var RDSDataServiceClient
     */
    private $client;

    /**
     * @var string
     */
    private $resourceArn;

    /**
     * @var string
     */
    private $secretArn;

    /**
     * @var string
     */
    private $database;

    /**
     * @var RdsDataConverter
     */
    private $dataConverter;

    /**
     * @var RdsDataStatement|null
     */
    private $lastStatement;

    /**
     * @var null|string
     */
    private $transactionId = null;

    /**
     * @var null|string
     */
    private $lastInsertedId;

    public function __construct(RDSDataServiceClient $client, string $resourceArn, string $secretArn, string $database)
    {
        $this->client = $client;
        $this->resourceArn = $resourceArn;
        $this->secretArn = $secretArn;
        $this->database = $database;
        $this->dataConverter = new RdsDataConverter();
    }

    public function __destruct()
    {
        // Since this connection is actually connectionless,
        // I want to make sure that transactions aren't left to time out after a request.
        $this->rollBack();
    }

    /**
     * @inheritDoc
     */
    public function prepare($prepareString): Statement
    {
        $this->lastStatement = new RdsDataStatement($this, $prepareString, $this->dataConverter);
        return $this->lastStatement;
    }

    /**
     * @param string $id
     *
     * @internal should only be used by the statement class
     */
    public function setLastInsertId(string $id)
    {
        $this->lastInsertedId = $id;
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId($name = null): string
    {
        return $this->lastInsertedId;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_BeginTransaction.html
     */
    public function beginTransaction()
    {
        if ($this->transactionId !== null) {
            return false;
        }

        $args = [
            'database' => $this->database,
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
        ];

        $response = $this->client->beginTransaction($args);
        $this->transactionId = $response['transactionId'];
        return true;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_CommitTransaction.html
     */
    public function commit()
    {
        if ($this->transactionId === null) {
            return false;
        }

        $args = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ];

        $this->client->commitTransaction($args);
        $this->transactionId = null;
        return true;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_RollbackTransaction.html
     */
    public function rollBack()
    {
        if ($this->transactionId === null) {
            return false;
        }

        $args = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ];

        $this->client->rollbackTransaction($args);
        $this->transactionId = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function errorCode()
    {
        if ($this->lastStatement === null) {
            return null;
        }

        return $this->lastStatement->errorCode();
    }

    /**
     * @inheritDoc
     */
    public function errorInfo()
    {
        if ($this->lastStatement === null) {
            return null;
        }

        return $this->lastStatement->errorInfo();
    }

    public function getClient(): RDSDataServiceClient
    {
        return $this->client;
    }

    public function getResourceArn(): string
    {
        return $this->resourceArn;
    }

    public function getSecretArn(): string
    {
        return $this->secretArn;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
}