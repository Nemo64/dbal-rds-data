<?php

namespace Nemo64\DbalRdsData;


use Doctrine\DBAL\ParameterType;

/**
 * This class provides methods to convert php data to the rds-data representation.
 *
 * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_Field.html
 */
class RdsDataConverter
{
    public function convertToJson($value, $type = ParameterType::STRING): array
    {
        switch ($value === null ? ParameterType::NULL : $type) {
            case ParameterType::NULL:
                return ['isNull' => true];
            case ParameterType::INTEGER:
                return ['longValue' => (int)$value];
            case ParameterType::STRING:
                return ['stringValue' => (string)$value];
            case ParameterType::LARGE_OBJECT:
                throw new \RuntimeException("LARGE_OBJECT not implemented.");
            case ParameterType::BOOLEAN:
                return ['booleanValue' => (bool)$value];
            case ParameterType::BINARY:
                $value = base64_encode(is_resource($value) ? stream_get_contents($value) : $value);
                return ['blobValue' => $value];
        }

        throw new \RuntimeException("Type $type is not implemented.");
    }

    /**
     * Results from rds are formatted in an array like this:
     * ['stringValue' => 'string']
     * ['longValue' => 5]
     * ['isNull' => true]
     *
     * This method converts this to a normal array that you'd expect.
     *
     * @param array $json
     *
     * @return mixed
     */
    public function convertToValue(array $json)
    {
        $key = key($json);
        $value = reset($json);

        switch ($key) {
            case 'isNull':
                return null;
            case 'blobValue':
                $resource = fopen('php://temp', 'rb+');
                fwrite($resource, base64_decode($value));
                return $resource;
            case 'arrayValue':
                throw new \RuntimeException("$key is not implemented.");
            case 'structValue':
                return array_map([$this, 'convertToValue'], $value);
            default:
                return $value;
        }
    }
}
