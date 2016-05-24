<?php

interface SMS
{
    public function send($to, $text);
}

class TwilioSMS implements SMS
{
    protected $sid;
    protected $token;
    protected $fromNumber;

    public function __construct($sid, $token, $fromNumber)
    {
        $this->sid = $sid;
        $this->token = $token;
        $this->fromNumber = $fromNumber;
    }

    public function send($to, $text)
    {
        return new Icicle\Coroutine\Coroutine((new \Icicle\Http\Client\Client())->request(
            'POST', 'https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Messages.json', [
                'Authorization' => 'Basic' . base64_encode($htis->sid . ':' . $this->token),
                'Content-type' => 'application/x-www-form-urlencoded'
            ], new \Icicle\Stream\MemorySink(http_build_query([
                'To' => $to,
                'From' => $this->fromNumber,
                'Body' => $text
            ]))));
    }
}

class NexmoSMS implements SMS
{
    protected $key;
    protected $secret;
    protected $fromNumber;

    public function __construct($key, $secret, $fromNumber)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->fromNumber = $fromNumber;
    }

    public function send($to, $text)
    {
        return new Icicle\Coroutine\Coroutine((new \Icicle\Http\Client\Client())->request(
            'POST', 'https://rest.nexmo.com/sms/json?' . http_build_query([
                'api_key' => $this->key,
                'api_secret' => $this->secret,
            ]), [
            'Content-type' => 'application/x-www-form-urlencoded'
            ], new \Icicle\Stream\MemorySink(http_build_query([
                'to' => $to,
                'from' => $this->fromNumber,
                'text' => $text
            ]))));
    }
}

class DummySMS implements SMS
{
    protected $waitMs;

    public function __construct($waitMs)
    {
        $this->waitMs = $waitMs;
    }

    public function send($to, $message)
    {
        usleep($this->waitMs * 1000);
        error_log("Dummy SMS: " . $to . " <- " . $message);
    }
}
