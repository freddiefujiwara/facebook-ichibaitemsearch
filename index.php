<?php
require_once __DIR__ . '/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;
$app->post('/', function (Request $request, Response $response) {
    // message from LINE server
    $body = json_decode($request->getBody(), true);
    error_log(__FILE__.":".__LINE__.":".print_r($body,true));

    foreach ($body['entry'] as $entry) {
        foreach ($entry['messaging'] as $msg) {
            //Search by Rakuten Web Service
            $rwsClient = new RakutenRws_Client();
            $rwsClient-> setApplicationId(getenv('RAKUTEN_WEBSERVICE_APPLICATIN_ID'));
            $rwsClient-> setAffiliateId(getenv('RAKUTEN_WEBSERVICE_AFFILIATE_ID'));
            $rwsResponse = $rwsClient->execute('IchibaItemSearch', array(
                'keyword' => $msg['content']['text'], // from message text
                'hits'    => 3,                       // #of Items
                'carrier' => 2                        // for smart phone
            ));
            if (!$rwsResponse->isOk()) {
                error_log(__FILE__.":".__LINE__.":".$rwsResponse->getMessage());
                continue;
            }

            //Respond message
            foreach ($rwsResponse['Items'] as $item) {
                $resContent = $msg['content'];
                $resContent['text'] = "";
                $resContent['text'] .= $item['Item']['itemName']."\n";
                $resContent['text'] .= $item['Item']['itemUrl'];

                $requestOptions = [
                    'body' => json_encode([
                        'recipient' => ['id' => [$msg['sender']['id']]],
                        'message'   => ['text' => $resContent]
                    ]),
                    'headers' => [
                        'Content-Type'                 => 'application/json'
                    ]
                ];

                $client = new GuzzleHttp\Client();
                try {
                    $client->request('post', 
                        'https://graph.facebook.com/v2.6/me/messages?access_token='.getenv('FACEBOOK_ACCES_TOKEN'), 
                        $requestOptions);
                } catch (Exception $e) {
                    error_log(__FILE__.":".__LINE__.":".$e->getMessage());
                }
            }
        }
    }

    return $response;
});
$app->get('/', function (Request $request, Response $response) {
    $query = $request -> getQueryParams();
    error_log(__FILE__.":".__LINE__.":".print_r($query,true));
    if ($query['hub_verify_token'] == getenv('FACEBOOK_VALIDATION_TOKEN')) {
        error_log(__FILE__.":".__LINE__);
        $response->getBody()->write($query['hub_challenge']);
    }
    error_log(__FILE__.":".__LINE__);
    $response->getBody()->write('Error, wrong validation token');
});
$app->run();
