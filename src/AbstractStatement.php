<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;

/**
 * Keep some filler methods away from the main implementation to make it simpler
 */
abstract class AbstractStatement implements \IteratorAggregate, Statement
{
    /**
     * @var int
     */
    protected $fetchMode = FetchMode::MIXED;

    /**
     * @inheritDoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $this->fetchMode = $fetchMode;
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
        return $this->fetch(FetchMode::NUMERIC)[$columnIndex] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        // The difference between bindValue and bindParam is that bindParam takes a reference.
        // https://stackoverflow.com/questions/1179874/what-is-the-difference-between-bindparam-and-bindvalue
        // I decided not to support that for simplicity.
        // It might create issues with some implementations that rely on that fact.
        return $this->bindParam($param, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Iterator
    {
        while (($row = $this->fetch()) !== false) {
            yield $row;
        }
    }
}
