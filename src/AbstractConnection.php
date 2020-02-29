<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * Keep some filler methods away from the main implementation to make it simpler
 */
abstract class AbstractConnection implements Connection
{
    /**
     * @inheritDoc
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        // TODO this isn't save against multibyte attacks
        trigger_error("quote isn't save against multibyte attacks", E_USER_WARNING);
        return "'" . addslashes($input) . "'";
    }

    /**
     * @inheritDoc
     */
    public function query(): Statement
    {
        $args = func_get_args();
        $sql = $args[0];
        $stmt = $this->prepare($sql);
        $stmt->execute();

        return $stmt;
    }

    /**
     * @inheritDoc
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_ExecuteStatement.html
     */
    public function exec($statement): int
    {
        $stmt = $this->prepare($statement);
        $affectedRows = $stmt->execute();
        $errorInfo = $stmt->errorInfo();
        if (!empty($errorInfo)) {
            throw new \Exception(reset($errorInfo), $stmt->errorCode());
        }

        return $affectedRows;
    }
}
