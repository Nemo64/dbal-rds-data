<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\Driver\AbstractDriverException;

/**
 * Class RdsDataException
 *
 * The rds data api does only provide the error message, not the error type.
 * This exception fixes this by extracting the error code from the message.
 *
 * @see https://forums.aws.amazon.com/thread.jspa?threadID=317595
 */
class RdsDataException extends AbstractDriverException
{
    /**
     * This expression is generated on the mysql documentation using the following script:
     *
     * "\"#^(\"\n    . " +
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
     *         return `${message}(*:${codes[0].textContent},${codes[1].textContent})`
     *     })
     *     .filter(d => d !== null)
     *     .map((v, i) => i > 0 ? `|${v}` : v)
     *     .map(v => JSON.stringify(v))
     *     .join("\n    . ")
     * + "\n    . \")$#\""
     *
     * @see https://dev.mysql.com/doc/refman/5.6/en/server-error-reference.html
     * @see \Doctrine\DBAL\Driver\AbstractMySQLDriver::convertException
     */
    private const EXPRESSION = "#^("
    . "Access denied for user '.*'@'.*' to database '.*'(*:1044,ER_DBACCESS_DENIED_ERROR)"
    . "|Access denied for user '.*'@'.*' \\(using password\\: .*\\)(*:1045,ER_ACCESS_DENIED_ERROR)"
    . "|No database selected(*:1046,ER_NO_DB_ERROR)"
    . "|Column '.*' cannot be null(*:1048,ER_BAD_NULL_ERROR)"
    . "|Unknown database '.*'(*:1049,ER_BAD_DB_ERROR)"
    . "|Table '.*' already exists(*:1050,ER_TABLE_EXISTS_ERROR)"
    . "|Unknown table '.*'(*:1051,ER_BAD_TABLE_ERROR)"
    . "|Column '.*' in .* is ambiguous(*:1052,ER_NON_UNIQ_ERROR)"
    . "|Unknown column '.*' in '.*'(*:1054,ER_BAD_FIELD_ERROR)"
    . "|Duplicate column name '.*'(*:1060,ER_DUP_FIELDNAME)"
    . "|Duplicate entry '.*' for key .*(*:1062,ER_DUP_ENTRY)"
    . "|.* near '.*' at line .*(*:1064,ER_PARSE_ERROR)"
    . "|You are not owner of thread .*(*:1095,ER_KILL_DENIED_ERROR)"
    . "|Column '.*' specified twice(*:1110,ER_FIELD_SPECIFIED_TWICE)"
    . "|Table handler doesn't support NULL in given index\\. Please change column '.*' to be NOT NULL or use another handler(*:1121,ER_NULL_COLUMN_IN_INDEX)"
    . "|Invalid use of NULL value(*:1138,ER_INVALID_USE_OF_NULL)"
    . "|.* command denied to user '.*'@'.*' for table '.*'(*:1142,ER_TABLEACCESS_DENIED_ERROR)"
    . "|.* command denied to user '.*'@'.*' for column '.*' in table '.*'(*:1143,ER_COLUMNACCESS_DENIED_ERROR)"
    . "|Table '.*\\..*' doesn't exist(*:1146,ER_NO_SUCH_TABLE)"
    . "|You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use(*:1149,ER_SYNTAX_ERROR)"
    . "|Incorrect column name '.*'(*:1166,ER_WRONG_COLUMN_NAME)"
    . "|All parts of a PRIMARY KEY must be NOT NULL; if you need NULL in a key, use UNIQUE instead(*:1171,ER_PRIMARY_CANT_HAVE_NULL)"
    . "|Lock wait timeout exceeded; try restarting transaction(*:1205,ER_LOCK_WAIT_TIMEOUT)"
    . "|Deadlock found when trying to get lock; try restarting transaction(*:1213,ER_LOCK_DEADLOCK)"
    . "|Cannot add or update a child row\\: a foreign key constraint fails(*:1216,ER_NO_REFERENCED_ROW)"
    . "|Cannot delete or update a parent row\\: a foreign key constraint fails(*:1217,ER_ROW_IS_REFERENCED)"
    . "|Access denied; you need \\(at least one of\\) the .* privilege\\(s\\) for this operation(*:1227,ER_SPECIFIC_ACCESS_DENIED_ERROR)"
    . "|All parts of a SPATIAL index must be NOT NULL(*:1252,ER_SPATIAL_CANT_HAVE_NULL)"
    . "|Column set to default value; NULL supplied to NOT NULL column '.*' at row .*(*:1263,ER_WARN_NULL_TO_NOTNULL)"
    . "|'.*' is deprecated and will be removed in a future release\\. Please use .* instead(*:1287,ER_WARN_DEPRECATED_SYNTAX)"
    . "|Malformed file type header in file '.*'(*:1341,ER_FPARSER_BAD_HEADER)"
    . "|Unexpected end of file while parsing comment '.*'(*:1342,ER_FPARSER_EOF_IN_COMMENT)"
    . "|Error while parsing parameter '.*' \\(line\\: '.*'\\)(*:1343,ER_FPARSER_ERROR_IN_PARAMETER)"
    . "|Unexpected end of file while skipping unknown parameter '.*'(*:1344,ER_FPARSER_EOF_IN_UNKNOWN_PARAMETER)"
    . "|Field '.*' doesn't have a default value(*:1364,ER_NO_DEFAULT_FOR_FIELD)"
    . "|.* command denied to user '.*'@'.*' for routine '.*'(*:1370,ER_PROCACCESS_DENIED_ERROR)"
    . "|The '.*' syntax is reserved for purposes internal to the MySQL server(*:1382,ER_RESERVED_SYNTAX)"
    . "|Unable to connect to foreign data source\\: .*(*:1429,ER_CONNECT_TO_FOREIGN_DATA_SOURCE)"
    . "|Cannot delete or update a parent row\\: a foreign key constraint fails \\(.*\\)(*:1451,ER_ROW_IS_REFERENCED_2)"
    . "|Cannot add or update a child row\\: a foreign key constraint fails \\(.*\\)(*:1452,ER_NO_REFERENCED_ROW_2)"
    . "|Syntax error\\: .* PARTITIONING requires definition of VALUES .* for each partition(*:1479,ER_PARTITION_REQUIRES_VALUES_ERROR)"
    . "|Failed to drop .*(*:1541,ER_EVENT_DROP_FAILED)"
    . "|The syntax '.*' is deprecated and will be removed in MySQL .*\\. Please use .* instead(*:1554,ER_WARN_DEPRECATED_SYNTAX_WITH_VER)"
    . "|Upholding foreign key constraints for table '.*', entry '.*', key .* would lead to a duplicate entry(*:1557,ER_FOREIGN_DUPLICATE_KEY)"
    . "|Not allowed to use NULL value in VALUES LESS THAN(*:1566,ER_NULL_IN_VALUES_LESS_THAN)"
    . "|ALTER TABLE causes auto_increment resequencing, resulting in duplicate entry '.*' for key '.*'(*:1569,ER_DUP_ENTRY_AUTOINCREMENT_CASE)"
    . "|Duplicate entry '.*' for key '.*'(*:1586,ER_DUP_ENTRY_WITH_KEY_NAME)"
    . "|Invalid column reference \\(.*\\) in LOAD DATA(*:1611,ER_LOAD_DATA_INVALID_COLUMN)"
    . "|Error in parsing conflict function\\. Message\\: .*(*:1626,ER_CONFLICT_FN_PARSE_ERROR)"
    . "|Cannot truncate a table referenced in a foreign key constraint \\(.*\\)(*:1701,ER_TRUNCATE_ILLEGAL_FK)"

    // this error is custom and specific to aurora serverless proxies
    // i fake it to indicate a socket connection error so implementations will correctly identify a connection error
    . "|Communications link failure .*(*:2002,CR_CONNECTION_ERROR)"

    . ")$#";

    public function __construct($message, $sqlState = null, $errorCode = null)
    {
        if (preg_match(self::EXPRESSION, $message, $match)) {
            [$errorCode, $sqlState] = explode(',', $match['MARK']);
        }

        parent::__construct($message, $sqlState, $errorCode);
    }

}
