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
     *         return `(*:${codes[0].textContent},${codes[1].textContent})${message}`
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
    . "((*:1044,ER_DBACCESS_DENIED_ERROR)Access denied for user '.*'@'.*' to database '.*'"
    . "|(*:1045,ER_ACCESS_DENIED_ERROR)Access denied for user '.*'@'.*' \\(using password\\: .*\\)"
    . "|(*:1046,ER_NO_DB_ERROR)No database selected"
    . "|(*:1048,ER_BAD_NULL_ERROR)Column '.*' cannot be null"
    . "|(*:1049,ER_BAD_DB_ERROR)Unknown database '.*'"
    . "|(*:1050,ER_TABLE_EXISTS_ERROR)Table '.*' already exists"
    . "|(*:1051,ER_BAD_TABLE_ERROR)Unknown table '.*'"
    . "|(*:1052,ER_NON_UNIQ_ERROR)Column '.*' in .* is ambiguous"
    . "|(*:1054,ER_BAD_FIELD_ERROR)Unknown column '.*' in '.*'"
    . "|(*:1060,ER_DUP_FIELDNAME)Duplicate column name '.*'"
    . "|(*:1062,ER_DUP_ENTRY)Duplicate entry '.*' for key .*"
    . "|(*:1064,ER_PARSE_ERROR).* near '.*' at line .*"
    . "|(*:1095,ER_KILL_DENIED_ERROR)You are not owner of thread .*"
    . "|(*:1110,ER_FIELD_SPECIFIED_TWICE)Column '.*' specified twice"
    . "|(*:1121,ER_NULL_COLUMN_IN_INDEX)Table handler doesn't support NULL in given index\\. Please change column '.*' to be NOT NULL or use another handler"
    . "|(*:1138,ER_INVALID_USE_OF_NULL)Invalid use of NULL value"
    . "|(*:1142,ER_TABLEACCESS_DENIED_ERROR).* command denied to user '.*'@'.*' for table '.*'"
    . "|(*:1143,ER_COLUMNACCESS_DENIED_ERROR).* command denied to user '.*'@'.*' for column '.*' in table '.*'"
    . "|(*:1146,ER_NO_SUCH_TABLE)Table '.*\\..*' doesn't exist"
    . "|(*:1149,ER_SYNTAX_ERROR)You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use"
    . "|(*:1166,ER_WRONG_COLUMN_NAME)Incorrect column name '.*'"
    . "|(*:1171,ER_PRIMARY_CANT_HAVE_NULL)All parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead"
    . "|(*:1205,ER_LOCK_WAIT_TIMEOUT)Lock wait timeout exceeded; try restarting transaction"
    . "|(*:1213,ER_LOCK_DEADLOCK)Deadlock found when trying to get lock; try restarting transaction"
    . "|(*:1216,ER_NO_REFERENCED_ROW)Cannot add or update a child row\\: a foreign key constraint fails"
    . "|(*:1217,ER_ROW_IS_REFERENCED)Cannot delete or update a parent row\\: a foreign key constraint fails"
    . "|(*:1227,ER_SPECIFIC_ACCESS_DENIED_ERROR)Access denied; you need \\(at least one of\\) the .* privilege\\(s\\) for this operation"
    . "|(*:1252,ER_SPATIAL_CANT_HAVE_NULL)All parts of a SPATIAL index must be NOT NULL"
    . "|(*:1263,ER_WARN_NULL_TO_NOTNULL)Column set to default value; NULL supplied to NOT NULL column '.*' at row .*"
    . "|(*:1287,ER_WARN_DEPRECATED_SYNTAX)'.*' is deprecated and will be removed in a future release\\. Please use .* instead"
    . "|(*:1341,ER_FPARSER_BAD_HEADER)Malformed file type header in file '.*'"
    . "|(*:1342,ER_FPARSER_EOF_IN_COMMENT)Unexpected end of file while parsing comment '.*'"
    . "|(*:1343,ER_FPARSER_ERROR_IN_PARAMETER)Error while parsing parameter '.*' \\(line\\: '.*'\\)"
    . "|(*:1344,ER_FPARSER_EOF_IN_UNKNOWN_PARAMETER)Unexpected end of file while skipping unknown parameter '.*'"
    . "|(*:1364,ER_NO_DEFAULT_FOR_FIELD)Field '.*' doesn't have a default value"
    . "|(*:1370,ER_PROCACCESS_DENIED_ERROR).* command denied to user '.*'@'.*' for routine '.*'"
    . "|(*:1382,ER_RESERVED_SYNTAX)The '.*' syntax is reserved for purposes internal to the MySQL server"
    . "|(*:1429,ER_CONNECT_TO_FOREIGN_DATA_SOURCE)Unable to connect to foreign data source\\: .*"
    . "|(*:1451,ER_ROW_IS_REFERENCED_2)Cannot delete or update a parent row\\: a foreign key constraint fails \\(.*\\)"
    . "|(*:1452,ER_NO_REFERENCED_ROW_2)Cannot add or update a child row\\: a foreign key constraint fails \\(.*\\)"
    . "|(*:1479,ER_PARTITION_REQUIRES_VALUES_ERROR)Syntax error\\: .* PARTITIONING requires definition of VALUES .* for each partition"
    . "|(*:1541,ER_EVENT_DROP_FAILED)Failed to drop .*"
    . "|(*:1554,ER_WARN_DEPRECATED_SYNTAX_WITH_VER)The syntax '.*' is deprecated and will be removed in MySQL .*\\. Please use .* instead"
    . "|(*:1557,ER_FOREIGN_DUPLICATE_KEY)Upholding foreign key constraints for table '.*', entry '.*', key .* would lead to a duplicate entry"
    . "|(*:1566,ER_NULL_IN_VALUES_LESS_THAN)Not allowed to use NULL value in VALUES LESS THAN"
    . "|(*:1569,ER_DUP_ENTRY_AUTOINCREMENT_CASE)ALTER TABLE causes auto_increment resequencing, resulting in duplicate entry '.*' for key '.*'"
    . "|(*:1586,ER_DUP_ENTRY_WITH_KEY_NAME)Duplicate entry '.*' for key '.*'"
    . "|(*:1611,ER_LOAD_DATA_INVALID_COLUMN)Invalid column reference \\(.*\\) in LOAD DATA"
    . "|(*:1626,ER_CONFLICT_FN_PARSE_ERROR)Error in parsing conflict function\\. Message\\: .*"
    . "|(*:1701,ER_TRUNCATE_ILLEGAL_FK)Cannot truncate a table referenced in a foreign key constraint \\(.*\\)"

    // this error is custom and specific to aurora serverless proxies
    // I decided to use 6xxx error codes for proxy errors since server errors are 1xxx and client errors 2xxx
    . "|(*:6000,PR_CONNECTION_ERROR)Communications link failure.*"

    . ")$#s"; // note the PCRE_DOTALL modifier

    public function __construct($message, $sqlState = null, $errorCode = null)
    {
        if (preg_match(self::EXPRESSION, $message, $match)) {
            [$errorCode, $sqlState] = explode(',', $match['MARK']);
        }

        parent::__construct($message, $sqlState, $errorCode);
    }

}
