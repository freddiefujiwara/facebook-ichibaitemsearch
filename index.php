<?php
require_once __DIR__ . '/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;
$app->post('/', function (Request $request, Response $response) {
    // message from Facebook server
    $body = json_decode($request->getBody(), true);

    foreach ($body['entry'] as $entry) {
        foreach ($entry['messaging'] as $msg) {
            //Search by Rakuten Web Service
            $rwsClient = new RakutenRws_Client();
            $rwsClient-> setApplicationId(getenv('RAKUTEN_WEBSERVICE_APPLICATIN_ID'));
            $rwsClient-> setAffiliateId(getenv('RAKUTEN_WEBSERVICE_AFFILIATE_ID'));
            $rwsResponse = $rwsClient->execute('IchibaItemSearch', array(
                'keyword' => $msg['message']['text'], // from message text
                'hits'    => 3,                       // #of Items
                'carrier' => 2                        // for smart phone
            ));
            if (!$rwsResponse->isOk()) {
                error_log(__FILE__.":".__LINE__.":".$rwsResponse->getMessage());
                continue;
            }

            //Respond message
            foreach ($rwsResponse['Items'] as $item) {
                $resContent = "";
                $resContent .= $item['Item']['itemName']."\n";
                $resContent .= $item['Item']['itemUrl'];

                $body = json_encode([
                        'recipient' => ['id' => $msg['sender']['id']],
                        'message'   => ['text' => $resContent]
                    ]);
                $requestOptions = [
                    'body' => $body,
                    'headers' => [
                        'Content-Type'                 => 'application/json'
                    ]
                ];

                $client = new GuzzleHttp\Client();
                try {
                    $client->request('post', 
                        'https://graph.facebook.com/v2.6/me/messages?access_token='.getenv('FACEBOOK_ACCESS_TOKEN'), 
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
    if ($query['hub_verify_token'] == getenv('FACEBOOK_VALIDATION_TOKEN')) {
        $response->getBody()->write($query['hub_challenge']);
        return;
    }
    error_log(__FILE__.":".__LINE__.'Error, wrong validation token');
    $response->getBody()->write('Error, wrong validation token');
});
$app->run();
