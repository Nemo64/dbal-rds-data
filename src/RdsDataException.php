<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\Driver\AbstractDriverException;

/**
 * Class RdsDataException
 *
 * The rds data api does only provide the error message, not the error code.
 * This exception fixes this by extracting the error code from the message.
 *
 * @see https://forums.aws.amazon.com/thread.jspa?threadID=317595
 */
class RdsDataException extends AbstractDriverException
{
    /**
     * This expression is generated on the mysql documentation using the following script:
     *
     * Array.from(document.querySelectorAll('.itemizedlist .listitem'))
     *     .map(item => {
     *         const paragraphs = item.getElementsByTagName('p');
     *         const codes = item.getElementsByTagName('code');
     *         if (!paragraphs[1]) {
     *             return null;
     *         }
     *
     *         // these are the codes that are defined here: \Doctrine\DBAL\Driver\AbstractMySQLDriver::convertException
     *         if (![1213,1205,1050,1051,1146,1216,1217,1451,1452,1701,1062,1557,1569,1586,1054,1166,1611,1052,1060,1110,1064,1149,1287,1341,1342,1343,1344,1382,1479,1541,1554,1626,1044,1045,1046,1049,1095,1142,1143,1227,1370,1429,2002,2005,1048,1121,1138,1171,1252,1263,1364,1566].includes(Number(codes[0].textContent))) {
     *             return null;
     *         }
     *
     *         const message = paragraphs[1].textContent.trim()
     *             .replace(/^Message:\s+/g, '')
     *             .replace(/[\.\\\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:\-\#]/g, c => `\\${c}`)
     *             .replace(/\s+/g, ' ')
     *             .replace(/%\w+/g, '.*');
     *         return `(*:${codes[0].textContent})${message}`
     *     })
     *     .filter(d => d !== null)
     *     .map((v, i) => i > 0 ? `|${v}` : v)
     *     .map(v => JSON.stringify(v))
     *     .join("\n    . ")
     *
     * @see https://dev.mysql.com/doc/refman/5.6/en/server-error-reference.html
     * @see \Doctrine\DBAL\Driver\AbstractMySQLDriver::convertException
     */
    private const EXPRESSION = "#^"
    . "((*:1044)Access denied for user '.*'@'.*' to database '.*'"
    . "|(*:1045)Access denied for user '.*'@'.*' \\(using password\\: .*\\)"
    . "|(*:1046)No database selected"
    . "|(*:1048)Column '.*' cannot be null"
    . "|(*:1049)Unknown database '.*'"
    . "|(*:1050)Table '.*' already exists"
    . "|(*:1051)Unknown table '.*'"
    . "|(*:1052)Column '.*' in .* is ambiguous"
    . "|(*:1054)Unknown column '.*' in '.*'"
    . "|(*:1060)Duplicate column name '.*'"
    . "|(*:1062)Duplicate entry '.*' for key .*"
    . "|(*:1064).* near '.*' at line .*"
    . "|(*:1095)You are not owner of thread .*"
    . "|(*:1110)Column '.*' specified twice"
    . "|(*:1121)Table handler doesn't support NULL in given index\\. Please change column '.*' to be NOT NULL or use another handler"
    . "|(*:1138)Invalid use of NULL value"
    . "|(*:1142).* command denied to user '.*'@'.*' for table '.*'"
    . "|(*:1143).* command denied to user '.*'@'.*' for column '.*' in table '.*'"
    . "|(*:1146)Table '.*\\..*' doesn't exist"
    . "|(*:1149)You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use"
    . "|(*:1166)Incorrect column name '.*'"
    . "|(*:1171)All parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead"
    . "|(*:1205)Lock wait timeout exceeded; try restarting transaction"
    . "|(*:1213)Deadlock found when trying to get lock; try restarting transaction"
    . "|(*:1216)Cannot add or update a child row\\: a foreign key constraint fails"
    . "|(*:1217)Cannot delete or update a parent row\\: a foreign key constraint fails"
    . "|(*:1227)Access denied; you need \\(at least one of\\) the .* privilege\\(s\\) for this operation"
    . "|(*:1252)All parts of a SPATIAL index must be NOT NULL"
    . "|(*:1263)Column set to default value; NULL supplied to NOT NULL column '.*' at row .*"
    . "|(*:1287)'.*' is deprecated and will be removed in a future release\\. Please use .* instead"
    . "|(*:1341)Malformed file type header in file '.*'"
    . "|(*:1342)Unexpected end of file while parsing comment '.*'"
    . "|(*:1343)Error while parsing parameter '.*' \\(line\\: '.*'\\)"
    . "|(*:1344)Unexpected end of file while skipping unknown parameter '.*'"
    . "|(*:1364)Field '.*' doesn't have a default value"
    . "|(*:1370).* command denied to user '.*'@'.*' for routine '.*'"
    . "|(*:1382)The '.*' syntax is reserved for purposes internal to the MySQL server"
    . "|(*:1429)Unable to connect to foreign data source\\: .*"
    . "|(*:1451)Cannot delete or update a parent row\\: a foreign key constraint fails \\(.*\\)"
    . "|(*:1452)Cannot add or update a child row\\: a foreign key constraint fails \\(.*\\)"
    . "|(*:1479)Syntax error\\: .* PARTITIONING requires definition of VALUES .* for each partition"
    . "|(*:1541)Failed to drop .*"
    . "|(*:1554)The syntax '.*' is deprecated and will be removed in MySQL .*\\. Please use .* instead"
    . "|(*:1557)Upholding foreign key constraints for table '.*', entry '.*', key .* would lead to a duplicate entry"
    . "|(*:1566)Not allowed to use NULL value in VALUES LESS THAN"
    . "|(*:1569)ALTER TABLE causes auto_increment resequencing, resulting in duplicate entry '.*' for key '.*'"
    . "|(*:1586)Duplicate entry '.*' for key '.*'"
    . "|(*:1611)Invalid column reference \\(.*\\) in LOAD DATA"
    . "|(*:1626)Error in parsing conflict function\\. Message\\: .*"
    . "|(*:1701)Cannot truncate a table referenced in a foreign key constraint \\(.*\\)"

    // this error is custom and specific to aurora serverless proxies
    // I decided to use 6xxx error codes for proxy errors since server errors are 1xxx and client errors 2xxx
    . "|(*:6000)Communications link failure.*"

    . ")$#s"; // note the PCRE_DOTALL modifier

    public function __construct($message, $sqlState = null, $errorCode = null)
    {
        if ($errorCode === null && preg_match(self::EXPRESSION, $message, $match)) {
            $errorCode = $match['MARK'];
        }

        parent::__construct($message, $sqlState, $errorCode);
    }

}
