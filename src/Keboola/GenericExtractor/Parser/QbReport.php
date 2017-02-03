<?php

namespace Keboola\GenericExtractor\Parser;

use Keboola\Json\Parser as JsonParser,
    Keboola\Json\Exception\JsonParserException,
    Keboola\Json\Exception\NoDataException,
    Keboola\Json\Struct;
use Keboola\Juicer\Config\Config,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Parser\ParserInterface;
use Keboola\Temp\Temp;
use Monolog\Logger as Monolog;

/**
 * Parse JSON results from REST API to CSV
 */
class QbReport implements ParserInterface
{
    /**
     * @var JsonParser
     */
    protected $parser;

    /**
     * @param JsonParser $parser
     */
    public function __construct(JsonParser $parser) {
        $this->parser = $parser;
    }

    /**
     * Parse the data
     * @param array $data shall be the response body
     * @param string $type data type
     */
    public function process(array $data, $type, $parentId = null)
    {
        try {
            $this->parser->process($data, $type, $parentId);
        } catch(NoDataException $e) {
            Logger::log('debug', "No data returned in '{$type}'");
        } catch(JsonParserException $e) {
            throw new UserException(
                "Error parsing response JSON: " . $e->getMessage(),
                500,
                $e,
                $e->getData()
            );
        }
    }

    /**
     * Return the results list
     * @return Table[]
     */
    public function getResults() {
        return $this->parser->getCsvFiles();
    }

    /**
     * @return JsonParser
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * @param Config $config // not used anywhere in real aps (yet? - analyze)
     * @param Logger $logger
     * @param Temp $temp
     * @param array $metadata
     * @return static
     */
    public static function create(Config $config, Monolog $logger, Temp $temp, array $metadata = [])
    {
        // TODO move this if to $this->validateStruct altogether
        if (!empty($metadata['json_parser.struct']) && is_array($metadata['json_parser.struct'])) {
            if (
                empty($metadata['json_parser.structVersion'])
                || $metadata['json_parser.structVersion'] != Struct::STRUCT_VERSION
            ) {
                // temporary
                $metadata['json_parser.struct'] = self::updateStruct($metadata['json_parser.struct']);
            }

            $struct = $metadata['json_parser.struct'];
        } else {
            $struct = [];
        }

        $rowsToAnalyze = null != $config && !empty($config->getRuntimeParams()["analyze"]) ? $config->getRuntimeParams()["analyze"] : -1;
        $parser = JsonParser::create($logger, $struct, $rowsToAnalyze);
        $parser->setTemp($temp);
        return new static($parser);
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return [
            'json_parser.struct' => $this->parser->getStruct()->getStruct(),
            'json_parser.structVersion' => $this->parser->getStructVersion()
        ];
    }

    protected static function updateStruct(array $struct)
    {
        foreach($struct as $type => &$children) {
            if (!is_array($children)) {
                throw new ApplicationException("Error updating struct at '{$type}', an array was expected");
            }

            foreach($children as $child => &$dataType) {
                if (in_array($dataType, ['integer', 'double', 'string', 'boolean'])) {
                    // Make scalars non-strict
                    $dataType = 'scalar';
                } elseif ($dataType == 'array') {
                    // Determine array types
                    if (!empty($struct["{$type}.{$child}"])) {
                        $childType = $struct["{$type}.{$child}"];
                        if (array_keys($childType) == ['data']) {
                            $dataType = 'arrayOfscalar';
                        } else {
                            $dataType = 'arrayOfobject';
                        }
                    }
                }
            }
        }

        return $struct;
    }
}
