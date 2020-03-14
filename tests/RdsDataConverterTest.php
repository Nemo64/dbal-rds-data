<?php

namespace Nemo64\DbalRdsData\Tests;


use Doctrine\DBAL\ParameterType;
use Nemo64\DbalRdsData\RdsDataConverter;
use PHPUnit\Framework\TestCase;

class RdsDataConverterTest extends TestCase
{
    public static function data()
    {
        return [
            [['blobValue' => base64_encode('hi')], 'hi', ParameterType::LARGE_OBJECT],
            [['booleanValue' => true], true, ParameterType::BOOLEAN],
            [['booleanValue' => false], false, ParameterType::BOOLEAN],
            // [['doubleValue' => 5.5], 5.5, ParameterType::STRING], // there is no official double type in dbal
            [['isNull' => true], null, ParameterType::NULL],
            [['isNull' => true], null, ParameterType::STRING],
            [['longValue' => 5], 5, ParameterType::INTEGER],
            [['stringValue' => 'hi'], 'hi', ParameterType::STRING],
        ];
    }

    /**
     * @dataProvider data
     */
    public function testConvertToValue($json, $php)
    {
        $converter = new RdsDataConverter();
        $convertedValue = $converter->convertToValue($json);

        if (is_resource($convertedValue)) {
            $convertedValue = stream_get_contents($convertedValue);
        }

        $this->assertEquals($php, $convertedValue);
    }

    /**
     * @dataProvider data
     */
    public function testConvertToJson($json, $php, $type)
    {
        $converter = new RdsDataConverter();
        $this->assertEquals($json, $converter->convertToJson($php, $type));
    }
}
