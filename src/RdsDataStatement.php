<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\Exception\RDSDataServiceException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

/**
 * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html
 * @see https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
 * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_ExecuteStatement.html
 */
class RdsDataStatement extends AbstractStatement
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
     * @var \Aws\Result
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html#executestatement
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
        if (isset($this->result['records'])) {
            $this->result['records'] = [];
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        return count($this->result['columnMetadata']);
    }

    /**
     * @inheritDoc
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if ($cursorOrientation !== \PDO::FETCH_ORI_NEXT) {
            throw new \RuntimeException("Cursor direction not implemented");
        }

        $result = current($this->result['records']);
        if (!is_array($result)) {
            return $result;
        }

        $result = $this->convertResultToFetchMode($result, $fetchMode ?? $this->fetchMode);

        // advance the pointer and return
        next($this->result['records']);
        return $result;
    }

    /**
     * @param array $result
     * @param int $fetchMode
     *
     * @return array|object
     */
    private function convertResultToFetchMode(array $result, int $fetchMode)
    {
        $numResult = array_map([$this->dataConverter, 'convertToValue'], $result);

        switch ($fetchMode) {
            case FetchMode::NUMERIC:
                return $numResult;

            case FetchMode::ASSOCIATIVE:
                $columnNames = array_column($this->result['columnMetadata'], 'label');
                return array_combine($columnNames, $numResult);

            case FetchMode::MIXED:
                $columnNames = array_column($this->result['columnMetadata'], 'label');
                return $numResult + array_combine($columnNames, $numResult);

            case FetchMode::STANDARD_OBJECT:
                $columnNames = array_column($this->result['columnMetadata'], 'label');
                return (object)array_combine($columnNames, $numResult);

            case FetchMode::COLUMN:
                return reset($numResult);

            default:
                throw new \RuntimeException("Fetch mode $fetchMode not supported");
        }
    }

    /**
     * @inheritDoc
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        return $this->parameterBag->bindParam($column, $variable, $type, $length);
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
            $this->result = $this->connection->getClient()->executeStatement($args);

            if (!empty($this->result['generatedFields'])) {
                // multiple generated values do not exist in mysql since there can only be one AUTO_INCREMENT column
                // https://stackoverflow.com/a/7188052
                $generatedValue = reset($this->result['generatedFields']);
                $generatedValue = $this->dataConverter->convertToValue($generatedValue);
                $this->connection->setLastInsertId($generatedValue);
            }

            return true;
        } catch (RDSDataServiceException $exception) {
            if ($exception->getAwsErrorCode() === 'BadRequestException') {
                // TODO There is no status code information in the error so it can't be correctly mapped
                // https://forums.aws.amazon.com/thread.jspa?threadID=317595
                throw new RdsDataException($exception->getAwsErrorMessage());
            }

            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        if (isset($this->result['numberOfRecordsUpdated'])) {
            return $this->result['numberOfRecordsUpdated'];
        }

        return count($this->result['records']);
    }
}
