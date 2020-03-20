[![Packagist Version](https://img.shields.io/packagist/v/Nemo64/dbal-rds-data)](https://packagist.org/packages/nemo64/dbal-rds-data)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/Nemo64/dbal-rds-data/Test?label=tests)](https://github.com/Nemo64/dbal-rds-data/actions?query=workflow%3ATest)
[![Packagist License](https://img.shields.io/packagist/l/Nemo64/dbal-rds-data)](https://github.com/Nemo64/dbal-rds-data/blob/master/LICENSE)
[![Packagist Downloads](https://img.shields.io/packagist/dm/Nemo64/dbal-rds-data)](https://packagist.org/packages/nemo64/dbal-rds-data)

# doctrine driver for the Aurora Serverless rds data api

This is a driver to use the aws [rds-data] api on projects
that are using [dbal] for their database access.

It emulates a MySQL connection including transactions.
However: the driver does never establish a persistent connection.

This is experimental. I implemented it in a symfony project
with the doctrine orm and with this driver it worked fine.
I tested the schema tools, migrations and transactions. 

## Why would you use it?

- The data api makes it possible to use a database in an aws hosting environment
  without the need for VPC's which are not that easy to set up,
  might cost money if you need internet access
  and slow down lambda function starts
  (which you can run php on using custom runtimes like [bref]).
- Your application does not need the database password in plain text.
  You just need access to the aws api which can be managed a lot better.
  (there are other ways to achieve the same but still, it is really easy with the data api)
- There might be a performance benefit due to not needing to establish
  a direct database connection and automatic pool management
  (which is unheard of in the php world).
  
## Why wouldn't you use it?

- This implementation isn't battle tested. Be prepared for problems. Have a look into the
  [Implementation Details](#implementation-details) section and see if you are comfortable with them.
- The [rds-data] api has size restrictions in the [ExecuteStatement] call
  which might become a problem when your application grows.
  I have ideas how to work around that but there is nothing implemented yet.
- The [rds-data] api is currently only available in [a few regions]. This limitation can be lifted any day though.
  However, at the moment of writing this, there is only 1 region in europe available.
- The [rds-data] api is only available with [Aurora Serverless] and this library also limits you to MySQL mode.
  If you plan on using other databases then you can't use the rds-data api and this library (yet).
  Here are alternatives you might want to consider:
  - Aurora Serverless in Postgres mode (although this can probably very easily be added here, I'm open to pull requests)
  - Aurora Classic to get an [SLA] or to benefit from reserved instance pricing on predictable workloads
  - Aurora Global for better availability and all the benefits of Aurora Classic
  - or even normal RDS to save money or use engines that are not emulated by Aurora 
  
All those disadvantages are not inherit to the new technology and can be removed
either by progress from AWS or by progress on this library.

## How to use it

First you must store your database credentials as [a secret] including the username.
Then make sure to correctly configure [access to your database] to use the secret and the database.
If you create a iam user then there is a "AmazonRDSDataFullAccess" policy that can be used directly.

If you use dbal directly than this is the way:

```php
<?php
$connectionParams = array(
    'driverClass' => \Nemo64\DbalRdsData\RdsDataDriver::class,
    'host' => 'eu-west-1', // the aws region
    'user' => '[aws-api-key]', // optional if it is defined in the environment 
    'password' => '[aws-api-secret]', // optional if it is defined in the environment
    'dbname' => 'mydb',
    'driverOptions' => [
        'resourceArn' => 'arn:aws:rds:eu-west-1:012345678912:cluster:database-1np9t9hdbf4mk',
        'secretArn' => 'arn:aws:secretsmanager:eu-west-1:012345678912:secret:db-password-tSo334',
    ]
);
$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
```


Or use the short url syntax which is easier to change using environment variables:

```php
<?php
$connectionParams = array(
    'driverClass' => \Nemo64\DbalRdsData\RdsDataDriver::class,
    'url' => '//eu-west-1/mydb'
        . '?driverOptions[resourceArn]=arn:aws:rds:eu-west-1:012345678912:cluster:database-1np9t9hdbf4mk'
        . '&driverOptions[secretArn]=arn:aws:secretsmanager:eu-west-1:012345678912:secret:db-password-tSo334'
);
$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
```


Since I developed in symfony project, I might as well add how to define the driver in symfony:

```yaml
# doctrine.yaml
doctrine:
    dbal:
        # the url can override the driver class
        # but I can't define this driver in the url which is why i made it the default
        # Doctrine\DBAL\DriverManager::parseDatabaseUrlScheme
        driver_class: Nemo64\DbalRdsData\RdsDataDriver
        url: '%env(resolve:DATABASE_URL)%'
```
```sh
# .env

# you must not include a driver in the database url
# in this case I also didn't include the aws tokens in the url 
DATABASE_URL=//eu-west-1/mydb?driverOptions[resourceArn]=arn&&driverOptions[secretArn]=arn

# the aws-sdk will pick those up
# they are automatically configured in lambda and ec2 environments 
#AWS_ACCESS_KEY_ID=...
#AWS_SECRET_ACCESS_KEY=...
#AWS_SESSION_TOKEN=...
```

Other than the configuration it should work exactly like any other dbal connection.

### Driver options

- `resourceArn` (string; required) this is the ARN of the database cluster.
  Go into the your [RDS-Management] > your database > Configuration and copy it from there.
- `secretArn` (string; required) this is the ARN of the secret that stores the database password.
  Go into the [SecretManager] > your secret and use the Secret ARN.
- `timeout` (int; default=45) The [timeout setting of guzzle]. Set to 0 for indefinite but that might not be useful.
  The rds-data api will block for a maximum of 45 seconds (see the [rds-data] docs).
  Schema update queries will automatically be executed with the `continueAfterTimeout` option.
  If you need to run long update queries than you might want to use the rds data client directly.
  Use `$dbalConnection->getWrappedConnection()->getClient()` to get the aws-sdk client.   

### CloudFormation

Sure, here is a CloudFormation template to configure [Aurora Serverless] and a [Secret],
putting both together and setting an environment variable with the needed information.

This might be [serverless] flavoured but you should get the hang of it.

```yaml

# [...]

  iamRoleStatements:
    # allow using the rds-data api
    # https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html#data-api.access
    - Effect: Allow
      Resource: '*' # it isn't supported to limit this
      Action:
        # https://docs.aws.amazon.com/IAM/latest/UserGuide/list_amazonrdsdataapi.html
        - rds-data:ExecuteStatement
        - rds-data:BeginTransaction
        - rds-data:CommitTransaction
        - rds-data:RollbackTransaction
    # this rds-data endpoint will use the same identity to get the secret 
    # so you need to be able to read the password secret
    - Effect: Allow
      Resource: !Ref DatabasePassword
      Action:
        # https://docs.aws.amazon.com/IAM/latest/UserGuide/list_awssecretsmanager.html
        - secretsmanager:GetSecretValue

# [...]

  environment:
    DATABASE_URL: !Join
      - ''
      - - '//' # rds-data is set to default because custom drivers can't be named in a way that they can be used here
        - !Ref AWS::Region # the hostname is the region
        - '/mydb'
        - '?driverOptions[resourceArn]='
        - !Join [':', ['arn:aws:rds', !Ref AWS::Region, !Ref AWS::AccountId, 'cluster', !Ref Database]]
        - '&driverOptions[secretArn]='
        - !Ref DatabasePassword

# [...]

  # Make sure that there is a default VPC in your account.
  # https://console.aws.amazon.com/vpc/home#vpcs:isDefault=true
  # If not, click "Actions" > "Create Default VPC"
  # While your applications doesn't need it, the database must still be provisioned into a VPC so use the default. 
  Database:
    Type: AWS::RDS::DBCluster # https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/aws-resource-rds-dbcluster.html
    Properties:
      Engine: aurora
      EngineMode: serverless
      EnableHttpEndpoint: true # https://stackoverflow.com/a/58759313 (not fully documented in every language yet)
      DatabaseName: 'mydb'
      MasterUsername: !Join ['', ['{{resolve:secretsmanager:', !Ref DatabasePassword, ':SecretString:username}}']]
      MasterUserPassword: !Join ['', ['{{resolve:secretsmanager:', !Ref DatabasePassword, ':SecretString:password}}']]
      BackupRetentionPeriod: 1 # day
      # https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/aws-properties-rds-dbcluster-scalingconfiguration.html
      ScalingConfiguration: {MinCapacity: 1, MaxCapactiy: 2, AutoPause: true}
  DatabasePassword:
    Type: AWS::SecretsManager::Secret # https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/aws-resource-secretsmanager-secret.html
    Properties:
      GenerateSecretString:
        SecretStringTemplate: '{"username": "admin"}'
        GenerateStringKey: "password"
        PasswordLength: 41 # max length of a mysql password
        ExcludeCharacters: '"@/\'
  DatabaseSecretAttachment:
    Type: AWS::SecretsManager::SecretTargetAttachment # https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/aws-resource-secretsmanager-secrettargetattachment.html
    Properties:
      SecretId: !Ref DatabasePassword
      TargetId: !Ref Database
      TargetType: AWS::RDS::DBCluster
```

## Implementation details

### Error handling

The rds data api only provides error messages, not error codes.
To correctly map those errors to dbal exceptions I use a huge regular expression.
See: [`Nemo64\DbalRdsData\RdsDataException::EXPRESSION`](src/RdsDataException.php#L49).
Since most of it is generated using the [mysql error documentation],
it should be fine but it might not be 100% reliable.
I asked for this in the [amazon developer forum] but haven't gotten a response yet.

### Paused databases

If an [Aurora Serverless] is paused, you'll get this error message:
> Communications link failure
> 
> The last packet sent successfully to the server was 0 milliseconds ago.
> The driver has not received any packets from the server.

I mapped this error message to error code `6000` (server errors are 1xxx and client errors 2xxx).
It'll also be converted to dbal's `Doctrine\DBAL\Exception\ConnectionException`
which existing application might already handle gracefully.
But the most important thing is that you can catch and handle code `6000` specifically
to better tell your user that the database is paused and will probably be available soon.

### Parameters in prepared statements

While [ExecuteStatement] does support parameters, it only supports named parameters.
Question mark placeholders need to be emulated (which is funny because
the `mysqli` driver only supports question mark placeholders and no named parameters).
This is achieved by replacing `?` with `:1`, `:2` etc.
The replacement algorithm will avoid replacing `?` within string literals but be aware that you
shouldn't mix heavy string literals and question mark placeholders, just to be safe.

### String literals

Every driver has some form of connection aware literal string escape function.
But because the rds-data api is connectionless, it doesn't have such a method (except for parameters of course).
To emulate the escape feature, a check for none ASCII characters is performed.
If the string is pure ASCII it'll just pass though `addslashes` and gets quotes.
If it has more exotic characters it'll be base64 encoded to prevent any chance of multibyte sql injection attacks.
This should work transparently in most situations but you should definitely avoid using the `literal` function
and instead use parameter binding whenever possible. 


[rds-data]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
[dbal]: https://www.doctrine-project.org/projects/doctrine-dbal/en/2.10/index.html
[bref]: https://bref.sh/
[ExecuteStatement]: https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_ExecuteStatement.html
[a few regions]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html#data-api.regions
[Aurora Serverless]: https://aws.amazon.com/de/rds/aurora/serverless/
[SLA]: https://aws.amazon.com/de/rds/aurora/sla/
[access to your database]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html#data-api.access
[a secret]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html#data-api.secrets
[Secret]: https://aws.amazon.com/de/secrets-manager/
[serverless]: https://serverless.com/
[RDS-Management]: https://console.aws.amazon.com/rds/home
[timeout setting of guzzle]: http://docs.guzzlephp.org/en/stable/request-options.html#timeout
[mysql error documentation]: https://dev.mysql.com/doc/refman/5.6/en/server-error-reference.html
[amazon developer forum]: https://forums.aws.amazon.com/thread.jspa?threadID=317595
[error code `2002`]: https://dv.mysql.com/doc/refman/5.6/en/client-error-reference.html#error_cr_connection_error
