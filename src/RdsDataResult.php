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
     * @var array
     */
    private $fetchMode = [FetchMode::MIXED, null];

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
        $this->fetchMode = func_get_args();
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

        $fetchMode = $fetchMode !== null ? [$fetchMode, null] : $this->fetchMode;
        $result = $this->convertResultToFetchMode($result, ...$fetchMode);

        // advance the pointer and return
        next($this->result['records']);
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
        return $this->fetch(FetchMode::NUMERIC)[$columnIndex];
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
                return $numResult[$fetchArgument ?? 0];

            case FetchMode::CUSTOM_OBJECT:
                try {
                    $class = new \ReflectionClass($fetchArgument);
                    $result = $class->newInstanceWithoutConstructor();

                    self::mapProperties($class, $result, $this->result['columnMetadata'], $numResult);

                    $constructor = $class->getConstructor();
                    if ($constructor !== null) {
                        $constructor->invokeArgs($result, (array)$ctorArgs);
                    }

                    return $result;
                } catch (\ReflectionException $e) {
                    throw new RdsDataException("could not fetch as class '$fetchArgument': {$e->getMessage()}", 0, $e);
                }

            default:
                throw new \RuntimeException("Fetch mode $fetchMode not supported");
        }
    }

    private static function mapProperties(\ReflectionClass $class, $result, array $metadata, array $numResult)
    {
        foreach ($metadata as $columnIndex => ['label' => $columnName]) {
            if ($class->hasProperty($columnName)) {
                $property = $class->getProperty($columnName);
                $property->setAccessible(true);
                $property->setValue($result, $numResult[$columnIndex]);
                continue;
            }

            $result->{$columnName} = $numResult[$columnIndex];
        }
    }
}
