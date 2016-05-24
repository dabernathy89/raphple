<?php

use Icicle\Http\Message\{Request, BasicResponse as Response};

return function(\FastRoute\RouteCollector $app) use ($c) {
    // incoming SMS webhooks

    $app->addRoute('POST', '/twilio', function (Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        parse_str(yield $req->getBody()->read(4096), $body);

        if ($rs->recordEntry($body['Body'], $body['From'])) {
            yield from $res->getBody()->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . $rs->getNameByCode($body['Body']) . " has been received!</Message>
            </Response>");
            return $res;
        }

        yield from $res->getBody()->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
        return $res;
    });

    $app->addRoute('GET', '/nexmo', functioN(Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        $uri = $req->getUri();
        $from = $uri->getQueryValue('msisdn');
        $code = $uri->getQueryValue('text');

        if ($rs->recordEntry($code, $from)) {
            $c['sms']->send($from, 'Your entry into ' . $rs->getNameByCode($code) . ' has been received!');
        }
        yield from $res->getBody()->end('Message received.');
        return $res->withStatus(200);
    });

    // end of webhooks

    $app->addRoute('GET', '/', function (Request $req, Response $res) use ($c) {
        return $c['view']->render($res, 'home.php');
    });

    $app->addRoute('POST', '/', function (Request $req, Response $res) use ($c) {
        parse_str(yield $req->getBody()->read(4096), $body);
        $items = trim($body['raffle_items']);
        $name = trim($body['raffle_name']);

        $errors = [];

        if (!strlen($items))
            $errors['raffle_name'] = true;
        if (!strlen($name))
            $errors['raffle_items'] = true;

        if (count($errors))
            return $c['view']->render($res, 'home.php',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);

        $id = $c['raffleService']->create($name, explode("\n", trim($items)));

        return $res->withHeader('Location', '/' . $id)->withStatus(302)
            ->withCookie('sid' . $id, $c['raffleService']->getSid($id));
    });

    $app->addRoute('GET', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!$rs->raffleExists($id))
            return $c['view']->renderNotFound($res);

        if ($req->getUri()->getQueryValue('show') === 'entrants') {
            $output = ['is_complete' => $rs->isComplete($id)];

            $numbers = $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($c['auth']->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            yield from $res->getBody()->end(json_encode($output));
            return $res;
        }

        if ($rs->isComplete($id))
            return $c['view']->render($res, 'finished.php', ['raffleName' => $rs->getName($id)]);

        return $c['view']->render($res, 'waiting.php', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $c['auth']->isAuthorized($req, $id) ? $rs->getEntrantPhoneNumbers($id) : null,
            'entrantCount' => $rs->getEntrantCount($id)
        ]);
    });

    $app->addRoute('POST', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!$rs->raffleExists($id))
            return $c['view']->renderNotFound($res);

        if (!$c['auth']->isAuthorized($req, $id))
            return $res->withHeader('Location', '/')->withStatus(302);

        $data = ['raffleName' => $rs->getName($id)];

        if (!$rs->isComplete($id))
            $data['winnerNumbers'] = $rs->complete($id);

        return $c['view']->render($res, 'finished.php', $data);
    });
};
