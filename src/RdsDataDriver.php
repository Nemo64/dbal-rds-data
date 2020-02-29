<?php

namespace Nemo64\DbalRdsData;


use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\Driver;

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
        ];

        if ($username !== null && $username !== 'root') {
            $options['credentials']['key'] = $username;
        }

        if ($password !== null) {
            $options['credentials']['secret'] = $password;
        }

        return new RdsDataConnection(
            new RDSDataServiceClient($options),
            $driverOptions['resourceArn'],
            $driverOptions['secretArn'],
            $params['dbname']
        );
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'rds-data';
    }
}
