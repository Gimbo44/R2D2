<?php
declare(strict_types=1);

namespace Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Model\Credentials;
use Model\ExhaustPort;
use Model\PrisonLocation;
use Model\Token;
use RuntimeException;
use Translator\Translator;

class ApiDeathStarClient implements DeathStarClient
{
    const DELETE_EXHAUST_PORT_URI = '/reactor/exhaust/%s';
    const GET_PRISONER_URI = 'prisoner/%s';

    /** @var Client */
    private $client;
    /** @var Credentials */
    private $credentials;
    /** @var Translator */
    private $translator;

    /**
     * @param Translator $translator
     * @param Client $client
     * @param Credentials $credentials
     */
    public function __construct(Translator $translator, Client $client, Credentials $credentials)
    {
        $this->client = $client;
        $this->credentials = $credentials;
        $this->translator = $translator;
    }

    /**
     * @return Token
     */
    private function getToken(): Token
    {
        $response = $this->client->post(
            '/Token',
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ],
                'auth' => [
                    $this->credentials->getId(), $this->credentials->getSecret()
                ]
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Unable to obtain oauth token from deathstar');
        }

        $body = json_decode((string)$response->getBody(), true);

        return new Token($body['access_token']);
    }

    /**
     * @param ExhaustPort $port
     * @return bool
     */
    public function deleteExhaustPortById(ExhaustPort $port): bool
    {
        try {
            $this->client->delete(
                sprintf(self::DELETE_EXHAUST_PORT_URI, $port),
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $this->getToken()),
                        'Content-Type'  => 'application/json',
                        'x-torpedoes'   => 2
                    ],
                ]
            );
        } catch (ClientException $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param string $prisoner
     * @return PrisonLocation
     * @throws \Exception
     */
    public function getLocationOfPrisoner(string $prisoner): PrisonLocation
    {
        $response = $this->client->get(
            sprintf(self::GET_PRISONER_URI, $prisoner),
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->getToken()),
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Unable to obtain prisoner information for %s', $prisoner));
        }

        $body = json_decode((string)$response->getBody(), true);

        return new PrisonLocation(
            $this->translator->translate($body['cell']),
            $this->translator->translate($body['block'])
        );
    }
}
