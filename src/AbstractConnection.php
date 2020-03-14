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
        // If the input isn't ASCII then I'm not even gonna try escaping it because of possible multibyte attacks.
        // I just encode the input as base64 and let mysql decode it again.
        // I'm not 100% sure this works everywhere but it's better to have a failing query than a security hole.
        // If you actually need to escape user input: always prefer using parameters.
        if (mb_detect_encoding($input) !== 'ASCII') {
            return sprintf("FROM_BASE64('%s')", base64_encode($input));
        }

        return sprintf("'%s'", addslashes($input));
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
        $success = $stmt->execute();

        $errorInfo = $stmt->errorInfo();
        if (!empty($errorInfo)) {
            throw new \Exception(reset($errorInfo), $stmt->errorCode());
        }

        if (!$success) {
            return 0;
        }

        return $stmt->rowCount();
    }
}
