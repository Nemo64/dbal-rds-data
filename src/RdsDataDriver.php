<?php

namespace Nemo64\DbalRdsData;


use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\RDSDataService\RDSDataServiceClient;
use Doctrine\DBAL\Driver;
use GuzzleHttp\Client;

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

        $options['http_handler'] = new GuzzleHandler(new Client([
            // all calls to the data-api will time out after 45 seconds
            // https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
            'timeout' => 45,
        ]));

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
