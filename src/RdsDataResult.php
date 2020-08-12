<?php

namespace Nemo64\DbalRdsData;


use AsyncAws\RdsDataService\Result\ExecuteStatementResponse;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;

class RdsDataResult implements \IteratorAggregate, ResultStatement
{
    /**
     * @var ExecuteStatementResponse
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html#executestatement
     */
    private $result;

    /**
     * @var RdsDataConverter
     */
    private $dataConverter;

    /**
     * @var array|null
     */
    private $records;

    /**
     * @var array
     */
    private $fetchMode = [FetchMode::MIXED, null];

    public function __construct(ExecuteStatementResponse $result, RdsDataConverter $dataConverter = null)
    {
        $this->result = $result;
        $this->dataConverter = $dataConverter ?? new RdsDataConverter();
        $this->records = $result->getRecords();
    }

    /**
     * @inheritDoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null): bool
    {
        $this->fetchMode = func_get_args();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function columnCount(): int
    {
        return count($this->result->getColumnMetadata());
    }

    /**
     * @inheritDoc
     */
    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if ($cursorOrientation !== \PDO::FETCH_ORI_NEXT) {
            throw new \RuntimeException("Cursor direction not implemented");
        }

        $result = current($this->records);
        if (!is_array($result)) {
            return $result;
        }

        $fetchModeParams = $fetchMode !== null ? [$fetchMode, null] : $this->fetchMode;
        $result = $this->convertResultToFetchMode($result, ...$fetchModeParams);

        // advance the pointer and return
        next($this->records);
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        $previousFetchMode =  $this->fetchMode;
        if ($fetchMode !== null) {
            $this->setFetchMode($fetchMode, $fetchArgument, $ctorArgs);
        }

        $result = iterator_to_array($this);
        $this->setFetchMode(...$previousFetchMode);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($columnIndex = 0)
    {
        $row = $this->fetch(FetchMode::NUMERIC);
        if (!is_array($row)) {
            return false;
        }

        return $row[$columnIndex] ?? false;
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
     * @inheritDoc
     */
    public function closeCursor(): bool
    {
        $this->records = null;
        return true;
    }

    /**
     * @see \Doctrine\DBAL\Driver\Statement::rowCount
     */
    public function rowCount(): int
    {
        $numberOfRecordsUpdated = $this->result->getNumberOfRecordsUpdated();
        if ($numberOfRecordsUpdated !== null) {
            return $numberOfRecordsUpdated;
        }

        return count($this->records);
    }

    /**
     * @param array $result
     * @param int $fetchMode
     * @param mixed $fetchArgument
     * @param null|array $ctorArgs
     *
     * @return array|object
     * @throws RdsDataException
     */
    private function convertResultToFetchMode(array $result, int $fetchMode, $fetchArgument = null, $ctorArgs = null)
    {
        $numericResult = array_map([$this->dataConverter, 'convertToValue'], $result);

        switch ($fetchMode) {
            case FetchMode::NUMERIC:
                return $numericResult;

            case FetchMode::ASSOCIATIVE:
                return array_combine($this->getColumnNames(), $numericResult);

            case FetchMode::MIXED:
                return $numericResult + array_combine($this->getColumnNames(), $numericResult);

            case FetchMode::STANDARD_OBJECT:
                return (object)array_combine($this->getColumnNames(), $numericResult);

            case FetchMode::COLUMN:
                return $numericResult[$fetchArgument ?? 0];

            case FetchMode::CUSTOM_OBJECT:
                try {
                    $class = new \ReflectionClass($fetchArgument);
                    $object = $class->newInstanceWithoutConstructor();
                    $this->mapProperties($class, $object, $numericResult);

                    $constructor = $class->getConstructor();
                    if ($constructor !== null) {
                        $constructor->invokeArgs($object, (array)$ctorArgs);
                    }

                    return $object;
                } catch (\ReflectionException $e) {
                    throw new RdsDataException("could not fetch as class '$fetchArgument': {$e->getMessage()}", 0, $e);
                }

            default:
                throw new \RuntimeException("Fetch mode $fetchMode not supported");
        }
    }

    /**
     * @return array
     */
    private function getColumnNames(): array
    {
        $result = [];

        foreach ($this->result->getColumnMetadata() as $columnIndex => $columnMetadata) {
            $result[$columnIndex] = $columnMetadata->getLabel();
        }

        return $result;
    }

    /**
     * @param \ReflectionClass $class
     * @param object $result
     * @param array $numericResult
     *
     * @throws \ReflectionException
     */
    private function mapProperties(\ReflectionClass $class, $result, array $numericResult): void
    {
        foreach ($this->result->getColumnMetadata() as $columnIndex => $columnMetadata) {
            if ($class->hasProperty($columnMetadata->getLabel())) {
                $property = $class->getProperty($columnMetadata->getLabel());
                $property->setAccessible(true);
                $property->setValue($result, $numericResult[$columnIndex]);
                continue;
            }

            $result->{$columnName} = $numericResult[$columnIndex];
        }
    }
}
