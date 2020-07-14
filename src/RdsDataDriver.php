<?php

namespace Nemo64\DbalRdsData;


use AsyncAws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\DriverException;
use Doctrine\DBAL\Exception as DBALException;

class RdsDataDriver extends Driver\AbstractMySQLDriver
{
    /**
     * @inheritDoc
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = []): Driver\Connection
    {
        $options = ['region' => $params['host']];

        if ($username !== null && $username !== 'root') {
            $options['accessKeyId'] = $username;
        }

        if ($password !== null) {
            $options['accessKeySecret'] = $password;
        }

        $resourceArn = $driverOptions['resourceArn'];
        $secretArn = $driverOptions['secretArn'];
        unset($driverOptions['resourceArn']);
        unset($driverOptions['secretArn']);

        $client = new RDSDataServiceClient(array_replace($options, $driverOptions));
        return new RdsDataConnection($client, $resourceArn, $secretArn, $params['dbname']);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'rds-data';
    }

    public function convertException($message, DriverException $exception)
    {
        if ($exception->getErrorCode() === '6000') {
            return new DBALException\ConnectionException($message, $exception);
        }

        return parent::convertException($message, $exception);
    }

}
