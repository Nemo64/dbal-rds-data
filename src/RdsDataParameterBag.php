<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\ParameterType;

class RdsDataParameterBag
{
    /**
     * This expression can be used to find numeric parameters in an sql statement.
     * It understands strings and prevents matching within them.
     */
    private const NUMERIC_PARAMETER_EXPRESSION = '/\?(?=([^\'"`]+|\'([^\']|\\\\\')*\'|"([^"]|\\\\")*"|`([^`]|\\\\`)*`)*$)/';

    /**
     * @var array
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html#shape-sqlparameter
     * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-rds-data-2018-08-01.html#executestatement
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_SqlParameter.html
     * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_Field.html
     */
    private $parameters = [];

    /**
     * @var RdsDataConverter
     */
    private $dataConverter;

    public function __construct(RdsDataConverter $dataConverter = null)
    {
        $this->dataConverter = $dataConverter ?? new RdsDataConverter();
    }

    /**
     * @inheritDoc
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        // The difference between bindValue and bindParam is that bindParam takes a reference.
        // https://stackoverflow.com/questions/1179874/what-is-the-difference-between-bindparam-and-bindvalue
        // I decided not to support that for simplicity.
        // It might create issues with some implementations that rely on that fact.
        return $this->bindParam($param, $value, $type);
    }

    /**
     * @inheritDoc
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        if ($length !== null) {
            throw new \RuntimeException("length parameter not implemented.");
        }

        $this->parameters[$column] = [&$variable, $type];
        return true;
    }

    /**
     * Because the variable is only "bound" and can therefore change,
     * I must convert the representation just before sending the query.
     *
     * @return array
     */
    public function getParameters(): array
    {
        $result = [];

        foreach ($this->parameters as $column => $arguments) {
            $result[] = [
                'name' => (string)$column,
                'value' => $this->dataConverter->convertToJson(...$arguments),
            ];
        }

        return $result;
    }

    /**
     * The rds data api only supports named parameters but most dbal implementations heavily use numeric parameters.
     *
     * This method converts "?" into ":0" parameters.
     *
     * @param string $sql
     *
     * @return string
     */
    public function prepareSqlStatement(string $sql): string
    {
        $numericParameters = array_filter(array_keys($this->parameters), 'is_int');
        if (count($numericParameters) <= 0) {
            return $sql;
        }

        // it is valid to start numeric parameters 0 and 1
        $index = min($numericParameters);
        if ($index !== 0 && $index !== 1) {
            throw new \LogicException("Numeric parameters must start with 0 or 1.");
        }

        $createParameter = static function () use (&$index) {
            return ':' . $index++;
        };

        $sql = preg_replace_callback(self::NUMERIC_PARAMETER_EXPRESSION, $createParameter, $sql);
        if (!is_string($sql)) {
            // snipped from https://www.php.net/manual/de/function.preg-last-error.php#124124
            $pregError = array_flip(array_filter(get_defined_constants(true)['pcre'], function ($value) {
                return substr($value, -6) === '_ERROR';
            }, ARRAY_FILTER_USE_KEY))[preg_last_error()] ?? 'unknown error';
            throw new \RuntimeException("sql param replacement failed: $pregError");
        }

        return $sql;
    }
}
