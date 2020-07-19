<?php


namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class CallbackStatement extends ArrayStatement implements Statement
{
    /**
     * @var callable
     */
    private $callback;

    public function __construct(callable $callback)
    {
        parent::__construct([]);
        $this->callback = $callback;
    }


    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        return false;
    }

    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        return false;
    }

    public function errorCode()
    {
        return false;
    }

    public function errorInfo()
    {
        return [];
    }

    public function execute($params = null)
    {
        return ($this->callback)() ?? true;
    }

    public function rowCount()
    {
        return 0;
    }
}