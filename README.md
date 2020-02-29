# doctrine driver for the rds data api

This is a driver to use the aws [rds-data] api on projects
that are using dbal for their database access.

This is experimental. I implemented it in a symfony project
with the doctrine orm and with this driver it worked fine.
I tested the schema tools, migrations and transactions. 

## Why would you use it?

- The data api makes it possible to use a database in an aws hosting environment
  without the need for VPC's which are not that easy to set up,
  might cost money if you need internet access
  and slow down lambda function starts if you use the awesome [bref] project. 
- Your application does not need the database password in plain text.
  You just need access to the aws api which can be managed a lot better.
  (there are other ways to achive the same but still, it is really easy with the data api)
- There might be a performance benefit due to not needing to establish
  a direct database connection and automatic pool management
  (which is unheard of in the php world)
  
## Why wouldn't you use it?

- This implementation isn't well tested. Be prepared for problems.
- The [rds-data] api has size restrictions in the [ExecuteStatement] call
  which might become a problem when your application grows.
  I have ideas how to work around that but there is nothing implemented yet.
- The [rds-data] api is currently only available in [a few regions].

## How to use it

If you use dbal directly than this is the way:

```php
<?php
$connectionParams = array(
    'driverClass' => \Nemo64\DbalRdsData\RdsDataDriver::class,
    'host' => 'eu-west-1', // the aws region
    'user' => '[aws-api-key]', // optional if it is defined in the environment 
    'password' => '[aws-api-secret]', // optional if it is defined in the environment
    'dbname' => 'mydb',
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
DATABASE_URL=//eu-west-1/mydb

# the aws-sdk will pick those up
# they are automatically configured in lambda and ec2 environments 
#AWS_ACCESS_KEY_ID=...
#AWS_SECRET_ACCESS_KEY=...
#AWS_SESSION_TOKEN=...
```

Other than the configuration it should work exactly like any other dbal connection.


[rds-data]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html
[bref]: https://bref.sh/
[ExecuteStatement]: https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_ExecuteStatement.html
[a few regions]: https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/data-api.html#data-api.regions
