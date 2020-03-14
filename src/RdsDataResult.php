<?php

namespace Nemo64\DbalRdsData;


use Aws\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;

class RdsDataResult implements \IteratorAggregate, ResultStatement
{
    /**
     * @var Result
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html#executestatement
     */
    private $result;

    /**
     * @var RdsDataConverter
     */
    private $dataConverter;

    /**
     * @var int
     */
    private $fetchMode = FetchMode::MIXED;

    public function __construct(Result $result, RdsDataConverter $dataConverter = null)
    {
        $this->result = $result;
        $this->dataConverter = $dataConverter ?? new RdsDataConverter();
    }

    /**
     * @inheritDoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null): bool
    {
        $this->fetchMode = $fetchMode;
        return true;
    }

    /**
     * This method is used to fill lastInsertId of the connection.
     *
     * @param string|null $name
     *
     * @return string|null
     * @see \Nemo64\DbalRdsData\RdsDataConnection::lastInsertId
     * @internal
     */
    public function lastInsertId($name = null): ?string
    {
        if (empty($this->result['generatedFields'])) {
            return null;
        }

        // multiple generated values do not exist in mysql since there can only be one AUTO_INCREMENT column
        // https://stackoverflow.com/a/7188052
        $generatedValue = reset($this->result['generatedFields']);
        return $this->dataConverter->convertToValue($generatedValue);
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
     * @inheritDoc
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if ($fetchArgument !== null || $ctorArgs !== null) {
            throw new \RuntimeException('$fetchArgument and $ctorArgs are not supported.');
        }

        $result = [];
        while (($row = $this->fetch($fetchMode)) !== false) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->fetch(FetchMode::COLUMN);
    }

    /**
     * @return \Iterator
     */
    public function getIterator(): \Iterator
    {
        while (($row = $this->fetch()) !== false) {
            yield $row;
        }
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
    public function closeCursor(): bool
    {
        if (isset($this->result['records'])) {
            $this->result['records'] = null;
        }

        return true;
    }

    /**
     * @see \Doctrine\DBAL\Driver\Statement::rowCount
     */
    public function rowCount(): int
    {
        if (isset($this->result['numberOfRecordsUpdated'])) {
            return $this->result['numberOfRecordsUpdated'];
        }

        if (isset($this->result['records'])) {
            return count($this->result['records']);
        }

        return 0;
    }
}
