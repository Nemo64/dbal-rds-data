<?php

namespace Nemo64\DbalRdsData;


use AsyncAws\RDSDataService\ValueObject\ArrayValue;
use AsyncAws\RDSDataService\ValueObject\Field;
use Doctrine\DBAL\ParameterType;

/**
 * This class provides methods to convert php data to the rds-data representation.
 *
 * @see https://docs.aws.amazon.com/rdsdataservice/latest/APIReference/API_Field.html
 */
class RdsDataConverter
{
    public function convertToJson($value, $type): Field
    {
        switch ($value === null ? ParameterType::NULL : $type) {
            case ParameterType::LARGE_OBJECT:
                if (is_resource($value)) {
                    rewind($value);
                    $value = stream_get_contents($value);
                }

                return new Field(['blobValue' => $value]);

            case ParameterType::BOOLEAN:
                return new Field(['booleanValue' => (bool)$value]);

            // missing double because there is no official double type

            case ParameterType::NULL:
                return new Field(['isNull' => true]);

            case ParameterType::INTEGER:
                return new Field(['longValue' => (int)$value]);

            case ParameterType::STRING:
                return new Field(['stringValue' => (string)$value]);
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
     * @param Field $json
     *
     * @return mixed
     */
    public function convertToValue(Field $field)
    {
        if ($field->getIsNull()) {
            return null;
        }

        $arrayValue = $field->getArrayValue();
        if ($arrayValue !== null) {
            return $this->convertArrayValue($arrayValue);
        }

        return $field->getBlobValue()
            ?? $field->getBooleanValue()
            ?? $field->getDoubleValue()
            ?? $field->getLongValue()
            ?? $field->getStringValue();
    }

    private function convertArrayValue(ArrayValue $arrayValue)
    {
        $arrayValues = $arrayValue->getArrayValues();
        if ($arrayValues !== null) {
            return array_map([$this, 'convertArrayValue'], $arrayValues);
        }

        return $arrayValue->getBooleanValues()
            ?? $arrayValue->getDoubleValues()
            ?? $arrayValue->getLongValues()
            ?? $arrayValue->getStringValues();
    }
}
