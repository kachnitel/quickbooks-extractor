<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\GenericExtractor,
    Keboola\GenericExtractor\Config\Api,
    Keboola\GenericExtractor\Authentication\OAuth10;
use Keboola\Juicer\Config\Config,
    Keboola\Juicer\Parser\Json,
    Keboola\Juicer\Common\Logger;
use Keboola\Temp\Temp;
// use GuzzleHttp\Client;

class GenericExtractorTest extends ExtractorTestCase
{
    /**
     * No change to JSON parser structure should happen when nothing is parsed!
     */
    public function testRunMetadataUpdate()
    {
        $logger = $this->getLogger('test', true);

        Logger::setLogger($logger);

        $meta = [
            'json_parser.struct' => [
                'tickets.via' => ['channel' => 'scalar', 'source' => 'object']
            ],
            'time' => [
                'previousStart' => 123
            ]
        ];

        $cfg = new Config('testApp', 'testCfg', []);
        $api = Api::create(['baseUrl' => 'http://example.com'], $cfg);
        $api->setAuth(new OAuth10([
            'oauth_api' => [
                'credentials' => [
                    '#data' => json_encode([
                        'realm_id' => 1234,
                        'oauth_token' => 'aaa',
                        'oauth_token_secret' => 'bbb'
                    ]),
                    'appKey' => 'asd123',
                    '#appSecret' => 'aassdd112233'
                ]
            ]
        ]));

        $ex = new GenericExtractor(new Temp);
        $ex->setLogger($logger);
        $ex->setApi($api);

        $ex->setMetadata($meta);
        $ex->run($cfg);
        $after = $ex->getMetadata();

        self::assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
        self::assertArrayHasKey('time', $after);
    }

    public function testGetParser()
    {
        $temp = new Temp;
        $parser = Json::create(new Config('testApp', 'testCfg', []), $this->getLogger(), $temp);

        $extractor = new GenericExtractor($temp);
        $extractor->setParser($parser);
        self::assertEquals($parser, $extractor->getParser());
    }
}
