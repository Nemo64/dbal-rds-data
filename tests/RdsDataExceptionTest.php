<?php

namespace Nemo64\DbalRdsData\Tests;


use Nemo64\DbalRdsData\RdsDataException;
use PHPUnit\Framework\TestCase;

class RdsDataExceptionTest extends TestCase
{
    public static function messages()
    {
        return [
            ["Table 'foo.bar' doesn't exist", 1146, 'ER_NO_SUCH_TABLE'],
            ["Duplicate entry 'foo' for key bar", 1062, 'ER_DUP_ENTRY'],
            ["You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use", 1149, 'ER_SYNTAX_ERROR'],
            ["Cannot truncate a table referenced in a foreign key constraint (foobar)", 1701, 'ER_TRUNCATE_ILLEGAL_FK'],
            // this is a specific rds proxy error
            ["Communications link failure The last packet sent successfully to the server was 0 milliseconds ago. The driver has not received any packets from the server.", 2002, 'CR_CONNECTION_ERROR'],
            ["Some never before seen of error", null, null],
        ];
    }

    /**
     * @dataProvider messages
     */
    public function testMessageParsing($message, $expectedCode, $expectedState)
    {
        $exception = new RdsDataException($message);
        $this->assertEquals($expectedCode, $exception->getErrorCode());
        $this->assertEquals($expectedState, $exception->getSQLState());
    }
}
