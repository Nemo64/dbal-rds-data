<?php

namespace Nemo64\DbalRdsData\Tests;


use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nemo64\DbalRdsData\RdsDataDriver;
use Nemo64\DbalRdsData\RdsDataException;
use PHPUnit\Framework\TestCase;

class RdsDataExceptionTest extends TestCase
{
    public static function messages()
    {
        return [
            [
                "Table 'foo.bar' doesn't exist",
                1146,
                TableNotFoundException::class,
            ],
            [
                "Duplicate entry 'foo' for key bar",
                1062,
                UniqueConstraintViolationException::class,
            ],
            [
                "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use",
                1149,
                SyntaxErrorException::class,
            ],
            [
                "Cannot truncate a table referenced in a foreign key constraint (foobar)",
                1701,
                ForeignKeyConstraintViolationException::class,
            ],
            [
                "Communications link failure\n\n"
                . "The last packet sent successfully to the server was 0 milliseconds ago. The driver has not received any packets from the server.",
                6000,
                ConnectionException::class,
            ],
            [
                "Some never before seen of error",
                null,
                DriverException::class,
            ],
        ];
    }

    /**
     * @dataProvider messages
     */
    public function testMessageParsing($message, $expectedCode, $expectedException)
    {
        $exception = new RdsDataException($message);
        $this->assertEquals($expectedCode, $exception->getErrorCode());

        $driver = new RdsDataDriver();
        $this->assertInstanceOf($expectedException, $driver->convertException($exception->getMessage(), $exception));
    }
}
