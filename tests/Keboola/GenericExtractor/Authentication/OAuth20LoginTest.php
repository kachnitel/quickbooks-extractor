<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\OAuth20Login;
use GuzzleHttp\Client,
    GuzzleHttp\Exception\ClientException,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;
use Keboola\Code\Builder;
use Keboola\Juicer\Client\RestClient;

class OAuth20LoginTest extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $guzzle = new Client(['base_url' => 'http://example.com/api']);

        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode((object) [ // auth
                'access_token' => 1234,
                'expires_in' => 3
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // auth
                'access_token' => 4321,
                'expires_in' => 3
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ])))
        ]);
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle);

        $oauthCredentials = [
            'appKey' => 1,
            '#appSecret' => 'two',
            '#data' => json_encode([
                'access_token' => '1234',
                'refresh_token' => 'asdf',
                'expires_in' => 3600
            ])
        ];

        $api = [
            'authentication' => [
                'loginRequest' => [
                    'endpoint' => 'auth/refresh',
                    'params' => ['refresh_token' => ['user' => 'refresh_token']],
                    'method' => 'POST'
                ],
                'apiRequest' => [
                    'query' => ['access_token' => 'access_token']
                ],
                'expires' => ['response' => 'expires_in', 'relative' => true]
            ]
        ];

        $auth = new OAuth20Login(['oauth_api' => ['credentials' => $oauthCredentials]], $api);
        $auth->authenticateClient($restClient);

        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);
        $restClient->download($request);
        sleep(5);
        $restClient->download($request);

        // test signature of the api request
        self::assertEquals('access_token=1234', (string) $history->getIterator()[1]['request']->getQuery());
        self::assertEquals('access_token=1234', (string) $history->getIterator()[2]['request']->getQuery());
        self::assertEquals('access_token=4321', (string) $history->getIterator()[4]['request']->getQuery());

        $expiry = self::getProperty(
            $restClient->getClient()->getEmitter()->listeners('before')[0][0],
            'expires'
        );
        self::assertEquals(time() + 3, $expiry);
    }
}
