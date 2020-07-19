<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\Exception\RDSDataServiceException;
use Aws\RDSDataService\RDSDataServiceClient;
use Aws\Result;
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
     * @var string|null
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

    /**
     * @var int
     */
    private $pauseRetries = 0;

    /**
     * @var int
     */
    private $pauseRetryDelay = 5;

    public function __construct(RDSDataServiceClient $client, string $resourceArn, string $secretArn, string $database = null)
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
        // allow selecting a database by "use database;" statement
        if (preg_match('#^\s*use\s+(?:(\w+)|`([^`]+)`)\s*;?\s*$#i', $prepareString, $match)) {
            return new CallbackStatement(function () use ($match) {
                $this->setDatabase($match[1] ?: $match[2]);
            });
        }

        $this->lastStatement = new RdsDataStatement($this, $prepareString, $this->dataConverter);
        return $this->lastStatement;
    }

    /**
     * @param string $id
     *
     * @internal should only be used by the statement class
     */
    public function setLastInsertId(string $id): void
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
     * @throws RdsDataException
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionId !== null) {
            return false;
        }

        $args = [
            'database' => $this->database,
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
        ];

        $response = $this->call('beginTransaction', $args);
        $this->transactionId = $response['transactionId'];
        return true;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_CommitTransaction.html
     * @throws RdsDataException
     */
    public function commit(): bool
    {
        if ($this->transactionId === null) {
            return false;
        }

        $args = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ];

        $this->call('commitTransaction', $args);
        $this->transactionId = null;
        return true;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_RollbackTransaction.html
     * @throws RdsDataException
     */
    public function rollBack(): bool
    {
        if ($this->transactionId === null) {
            return false;
        }

        $args = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ];

        $this->call('rollbackTransaction', $args);
        $this->transactionId = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function errorCode(): ?string
    {
        if ($this->lastStatement === null) {
            return null;
        }

        return $this->lastStatement->errorCode();
    }

    /**
     * @inheritDoc
     */
    public function errorInfo(): array
    {
        if ($this->lastStatement === null) {
            return [];
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

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function setDatabase(?string $database): void
    {
        $this->database = $database;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * @return int
     */
    public function getPauseRetries(): int
    {
        return $this->pauseRetries;
    }

    /**
     * @param int $pauseRetries
     */
    public function setPauseRetries(int $pauseRetries): void
    {
        $this->pauseRetries = $pauseRetries;
    }

    /**
     * @return int
     */
    public function getPauseRetryDelay(): int
    {
        return $this->pauseRetryDelay;
    }

    /**
     * @param int $pauseRetryDelay
     */
    public function setPauseRetryDelay(int $pauseRetryDelay): void
    {
        $this->pauseRetryDelay = $pauseRetryDelay;
    }

    /**
     * Runs a rds data command and handles errors.
     *
     * @param string $command
     * @param array $args
     * @param int $retry
     * @return Result
     * @throws RdsDataException
     */
    public function call(string $command, array $args, int $retry = 0): Result
    {
        try {
            return $this->client->__call($command, [$args]);
        } catch (RDSDataServiceException $exception) {
            if ($exception->getAwsErrorCode() !== 'BadRequestException') {
                throw $exception;
            }

            $interpretedException = RdsDataException::interpretErrorMessage($exception->getAwsErrorMessage());
            if ($interpretedException->getErrorCode() === '6000' && $this->getPauseRetries() > $retry) {
                sleep($this->getPauseRetryDelay());
                return $this->call($command, $args, $retry + 1);
            }

            throw $interpretedException;
        }
    }
}
