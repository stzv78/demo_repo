<?php


namespace App\Services;

use App\Http\Resources\DepositResource;

class NRISApiService
{

    protected $client;

    protected $host;

    protected $XApiKey;

    protected $response;

    protected $exception;


    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client(['timeout' => 5, 'http_errors' => false]);
        $this->host = env('NRIS_HOST');
        $this->XApiKey = env('NRIS_X_API_KEY');
    }

    public function getResponseData()
    {
        return json_decode($this->response->getBody(), true);
    }

    public function getResponseCode()
    {
        return $this->response->getStatusCode();
    }

    public function isResponseSuccess()
    {
        return ($this->getResponseCode() == 200);
    }

    public function createOis(DepositResource $depositResource)
    {
        try {
            $this->response = $this->client->post(
                $this->host . '/api/ois/create',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Api-Key' => $this->XApiKey,
                    ],
                    'body' => json_encode($depositResource)
                ]
            );

        } catch (\Exception $exception) {
            $this->exception = $exception;
        }

        return $this;
    }
}
