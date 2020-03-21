<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\Exception\RDSDataServiceException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html
 * @see https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
 * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_ExecuteStatement.html
 */
class RdsDataStatement implements \IteratorAggregate, Statement
{
    /**
     * This expression is used to detect DDL queries.
     * Basically queries that modify the schema and can't be executed in a transaction.
     *
     * @see https://en.wikipedia.org/wiki/Data_definition_language
     */
    private const DDL_REGEX = '#^\s*(CREATE|DROP|ALTER|TRUNCATE)\s+(TABLE|INDEX|VIEW)#Si';

    /**
     * @var RdsDataConnection
     */
    private $connection;

    /**
     * @var RdsDataConverter
     */
    private $dataConverter;

    /**
     * @var RdsDataParameterBag
     */
    private $parameterBag;

    /**
     * @var string
     */
    private $sql;

    /**
     * @var RdsDataResult
     */
    private $result;

    public function __construct(RdsDataConnection $connection, string $sql, RdsDataConverter $dataConverter = null)
    {
        $this->connection = $connection;
        $this->dataConverter = $dataConverter ?? new RdsDataConverter();
        $this->parameterBag = new RdsDataParameterBag($this->dataConverter);
        $this->sql = $sql;
    }

    /**
     * @inheritDoc
     */
    public function closeCursor(): bool
    {
        // there is not really a cursor but I can free the memory the records are taking up.
        $this->result = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        return $this->result->columnCount();
    }

    /**
     * @inheritDoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null): bool
    {
        return $this->result->setFetchMode($fetchMode, $arg2, $arg3);
    }

    /**
     * @inheritDoc
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->result->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->result->fetchColumn($columnIndex);
    }

    /**
     * @inheritDoc
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->result->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @inheritDoc
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        return $this->parameterBag->bindParam($column, $variable, $type, $length);
    }

    /**
     * @inheritDoc
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return $this->parameterBag->bindValue($param, $value, $type);
    }

    /**
     * Returns the sql that can be used in a query.
     *
     * There is one big modification needed:
     * Doctrine polyfills named parameters to numbered parameters.
     * The rds-data api _only_ supports named parameters.
     *
     * But numbered parameters aren't straight forward too.
     * Some implementations start the numbers with 1 and others with 0.
     *
     * @return string
     */
    private function getSql(): string
    {
        return $this->parameterBag->prepareSqlStatement($this->sql);
    }

    /**
     * @inheritDoc
     */
    public function errorCode()
    {
        // TODO: Implement errorCode() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function errorInfo()
    {
        // TODO: Implement errorInfo() method.
        return [];
    }

    /**
     * @inheritDoc
     * @throws RdsDataException
     */
    public function execute($params = null): bool
    {
        if (is_iterable($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $this->bindValue($paramKey, $paramValue);
            }
        }

        $args = [
            'continueAfterTimeout' => preg_match(self::DDL_REGEX, $this->sql) > 0,
            'database' => $this->connection->getDatabase(),
            'includeResultMetadata' => true,
            'parameters' => $this->parameterBag->getParameters(),
            'resourceArn' => $this->connection->getResourceArn(), // REQUIRED
            'resultSetOptions' => [
                'decimalReturnType' => 'STRING',
            ],
            // 'schema' => '<string>',
            'secretArn' => $this->connection->getSecretArn(), // REQUIRED
            'sql' => $this->getSql(), // REQUIRED
        ];

        $transactionId = $this->connection->getTransactionId();
        if ($transactionId) {
            $args['transactionId'] = $transactionId;
        }

        try {
            $result = $this->connection->getClient()->executeStatement($args);

            if (!empty($result['generatedFields'])) {
                $generatedValue = $this->dataConverter->convertToValue(reset($result['generatedFields']));
                $this->connection->setLastInsertId($generatedValue);
            }

            $this->result = new RdsDataResult($result, $this->dataConverter);
            return true;
        } catch (RDSDataServiceException $exception) {
            if ($exception->getAwsErrorCode() === 'BadRequestException') {
                throw RdsDataException::interpretErrorMessage($exception->getAwsErrorMessage());
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        return $this->result->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        return $this->result->getIterator();
    }
}
