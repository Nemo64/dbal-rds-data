<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\Connection;
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
        $options = [
            'version' => '2018-08-01',
            'region' => $params['host'],
            'http' => [
                // all calls to the data-api will time out after 45 seconds
                // https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
                'timeout' => $driverOptions['timeout'] ?? 45,
            ],
        ];

        if ($username !== null && $username !== 'root') {
            $options['credentials']['key'] = $username;
        }

        if ($password !== null) {
            $options['credentials']['secret'] = $password;
        }

        $connection = new RdsDataConnection(
            new RDSDataServiceClient($options),
            $driverOptions['resourceArn'],
            $driverOptions['secretArn'],
            $params['dbname'] ?? null
        );

        $connection->setPauseRetries($driverOptions['pauseRetries'] ?? 0);
        $connection->setPauseRetryDelay($driverOptions['pauseRetryDelay'] ?? 10);

        return $connection;
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
        switch ($exception->getErrorCode()) {
            case '6000':
                return new DBALException\ConnectionException($message, $exception);

            default:
                return parent::convertException($message, $exception);
        }
    }

    public function getDatabase(Connection $conn)
    {
        $params = $conn->getParams();
        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        $connection = $conn->getWrappedConnection();
        if (!$connection instanceof RdsDataConnection) {
            return null;
        }

        return $connection->getDatabase();
    }
}
