<?php
require_once __DIR__ . '/vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;
$app->post('/', function (Request $request, Response $response) {
    // message from LINE server
    $body = json_decode($request->getBody(), true);

    foreach ($body['result'] as $msg) {
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
                    'to'        => [$msg['content']['from']],
                    'toChannel' => 1383378250,           // Fixed value
                    'eventType' => '138311608800106203', // Fixed value
                    'content'   => $resContent,
                ]),
                'headers' => [
                    'Content-Type'                 => 'application/json; charset=UTF-8',
                    'X-Line-ChannelID'             => getenv('LINE_CHANNEL_ID'),
                    'X-Line-ChannelSecret'         => getenv('LINE_CHANNEL_SECRET'),
                    'X-Line-Trusted-User-With-ACL' => getenv('LINE_CHANNEL_MID'),
                ],
                'proxy' => [
                    'https' => getenv('FIXIE_URL'),
                    ]
                ];

            $client = new GuzzleHttp\Client();
            try {
                $client->request('post', 'https://trialbot-api.line.me/v1/events', $requestOptions);
            } catch (Exception $e) {
                error_log(__FILE__.":".__LINE__.":".$e->getMessage());
            }
        }
    }

    return $response;
});
$app->run();
