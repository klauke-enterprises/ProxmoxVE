<?php

/**
 * This file is part of the ProxmoxVE PHP API wrapper library (unofficial).
 *
 * @copyright 2014 César Muñoz <zzantares@gmail.com>
 * @license http://opensource.org/licenses/MIT The MIT License.
 */

namespace ProxmoxVE;

use GuzzleHttp\Client;

/**
 * ProxmoxVE class. In order to interact with the proxmox server, the desired
 * app's code needs to create and use an object of this class.
 *
 * @author César Muñoz <zzantares@gmail.com>
 */
class Proxmox
{
    /**
     * @var \GuzzleHttp\Client()
     */
    private $httpClient;

    /**
     * Holds the response type used to requests the API, possible values are
     * json, extjs, html, text, png.
     *
     * @var string
     */
    private $responseType;


    /**
     * Holds the fake response type, it is useful when you want to get the JSON
     * raw string instead of a PHP array.
     *
     * @var string
     */
    private $fakeType;
    /**
     * @var string
     */
    private $tokenId;
    /**
     * @var string
     */
    private $tokenSecret;
    /**
     * @var string
     */
    private $hostname;
    /**
     * @var int
     */
    private $port;


    /**
     * Constructor.
     *
     * @param mixed $credentials Credentials object or associative array holding
     *                           the login data.
     * @param string $responseType The response type that is going to be returned when doing requests.
     * @param \GuzzleHttp\Client $httpClient The HTTP client to be used to send requests over the network.
     *
     * @throws \ProxmoxVE\Exception\MalformedCredentialsException If bad args
     *                                                            supplied.
     * @throws \ProxmoxVE\Exception\AuthenticationException If given credentials
     *                                                      are not valid.
     */
    public function __construct(
        $tokenId = "",
        $tokenSecret = "",
        $hostname = "",
        $port = 8006,
        $responseType = 'array',
        $httpClient = null
    )
    {
        $this->tokenId = $tokenId;
        $this->tokenSecret = $tokenSecret;
        $this->setHttpClient($httpClient);
        $this->setResponseType($responseType);
        $this->hostname = $hostname;
        $this->port = $port;
    }


    /**
     * Send a request to a given Proxmox API resource.
     *
     * @param string $actionPath The resource tree path you want to request, see
     *                           more at http://pve.proxmox.com/pve2-api-doc/
     * @param array $params An associative array filled with params.
     * @param string $method HTTP method used in the request, by default
     *                           'GET' method will be used.
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \InvalidArgumentException If the given HTTP method is not one of
     *                                   'GET', 'POST', 'PUT', 'DELETE',
     */
    private function requestResource($actionPath, $params = [], $method = 'GET', $json = false)
    {
        $url = $this->getApiUrl() . $actionPath;
        $headers = [
            "Authorization" => "PVEAPIToken=$this->tokenId=$this->tokenSecret"
        ];
        switch ($method) {
            case 'GET':
                return $this->httpClient->get($url, [
                    'http_errors' => false,
                    'headers' => $headers,
                    'query' => $params,
                ]);
            case 'POST':
            case 'PUT':
            case 'DELETE':
                $request_config = [
                    'http_errors' => false,
                    'headers' => $headers,
                ];
                if ($json) {
                    $request_config['json'] = $params;
                } else {
                    $request_config['form_params'] = $params;
                }
                return $this->httpClient->request($method, $url, $request_config);
            default:
                $errorMessage = "HTTP Request method {$method} not allowed.";
                throw new \InvalidArgumentException($errorMessage);
        }
    }

    public function getApiUrl()
    {
        return "https://{$this->hostname}:{$this->port}/api2/{$this->responseType}";
    }

    /**
     * Parses the response to the desired return type.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response sent by the Proxmox server.
     *
     * @return mixed The parsed response, depending on the response type can be
     *               an array or a string.
     */
    private function processHttpResponse($response)
    {
        if ($response === null) {
            return null;
        }

        switch ($this->fakeType) {
            case 'pngb64':
                $base64 = base64_encode($response->getBody());
                return 'data:image/png;base64,' . $base64;
            case 'object': // 'object' not supported yet, we return array instead.
            case 'array':
                return json_decode($response->getBody(), true);
            default:
                return $response->getBody()->__toString();
        }
    }


    /**
     * Sets the HTTP client to be used to send requests over the network, for
     * now Guzzle needs to be used.²
     *
     * @param \GuzzleHttp\Client $httpClient the client to be used
     */
    public function setHttpClient($httpClient = null)
    {
        $this->httpClient = $httpClient ?: new Client();
    }

    /**
     * Sets the response type that is going to be returned when doing requests.
     *
     * @param string $responseType One of json, html, extjs, text, png.
     */
    public function setResponseType($responseType = 'array')
    {
        $supportedFormats = array('json', 'html', 'extjs', 'text', 'png');

        if (in_array($responseType, $supportedFormats)) {
            $this->fakeType = false;
            $this->responseType = $responseType;
        } else {
            switch ($responseType) {
                case 'pngb64':
                    $this->fakeType = 'pngb64';
                    $this->responseType = 'png';
                    break;
                case 'object':
                case 'array':
                    $this->responseType = 'json';
                    $this->fakeType = $responseType;
                    break;
                default:
                    $this->responseType = 'json';
                    $this->fakeType = 'array'; // Default format
            }
        }
    }


    /**
     * Returns the response type that is being used by the Proxmox API client.
     *
     * @return string Response type being used.
     */
    public function getResponseType()
    {
        return $this->fakeType ?: $this->responseType;
    }


    /**
     * GET a resource defined in the pvesh tool.
     *
     * @param string $actionPath The resource tree path you want to ask for, see
     *                           more at http://pve.proxmox.com/pve2-api-doc/
     * @param array $params An associative array filled with params.
     *
     * @return array             A PHP array json_decode($response, true).
     *
     * @throws \InvalidArgumentException If given params are not an array.
     */
    public function get($actionPath, $params = [])
    {
        if (!is_array($params)) {
            $errorMessage = 'GET params should be an associative array.';
            throw new \InvalidArgumentException($errorMessage);
        }

        // Check if we have a prefixed '/' on the path, if not add one.
        if (substr($actionPath, 0, 1) != '/') {
            $actionPath = '/' . $actionPath;
        }

        $response = $this->requestResource($actionPath, $params);
        return $this->processHttpResponse($response);
    }


    /**
     * SET a resource defined in the pvesh tool.
     *
     * @param string $actionPath The resource tree path you want to ask for, see
     *                           more at http://pve.proxmox.com/pve2-api-doc/
     * @param array $params An associative array filled with params.
     *
     * @return array             A PHP array json_decode($response, true).
     *
     * @throws \InvalidArgumentException If given params are not an array.
     */
    public function set($actionPath, $params = [], $json = false)
    {
        if (!is_array($params)) {
            $errorMessage = 'PUT params should be an associative array.';
            throw new \InvalidArgumentException($errorMessage);
        }

        // Check if we have a prefixed '/' on the path, if not add one.
        if (substr($actionPath, 0, 1) != '/') {
            $actionPath = '/' . $actionPath;
        }

        $response = $this->requestResource($actionPath, $params, 'PUT', $json);
        return $this->processHttpResponse($response);
    }


    /**
     * CREATE a resource as defined by the pvesh tool.
     *
     * @param string $actionPath The resource tree path you want to ask for, see
     *                           more at http://pve.proxmox.com/pve2-api-doc/
     * @param array $params An associative array filled with POST params
     *
     * @return array             A PHP array json_decode($response, true).
     *
     * @throws \InvalidArgumentException If given params are not an array.
     */
    public function create($actionPath, $params = [], $json = false)
    {
        if (!is_array($params)) {
            $errorMessage = 'POST params should be an asociative array.';
            throw new \InvalidArgumentException($errorMessage);
        }

        // Check if we have a prefixed '/' on the path, if not add one.
        if (substr($actionPath, 0, 1) != '/') {
            $actionPath = '/' . $actionPath;
        }

        $response = $this->requestResource($actionPath, $params, 'POST', $json);
        return $this->processHttpResponse($response);
    }


    /**
     * DELETE a resource defined in the pvesh tool.
     *
     * @param string $actionPath The resource tree path you want to ask for, see
     *                           more at http://pve.proxmox.com/pve2-api-doc/
     * @param array $params An associative array filled with params.
     *
     * @return array             A PHP array json_decode($response, true).
     *
     * @throws \InvalidArgumentException If given params are not an array.
     */
    public function delete($actionPath, $params = [], $json = false)
    {
        if (!is_array($params)) {
            $errorMessage = 'DELETE params should be an associative array.';
            throw new \InvalidArgumentException($errorMessage);
        }

        // Check if we have a prefixed '/' on the path, if not add one.
        if (substr($actionPath, 0, 1) != '/') {
            $actionPath = '/' . $actionPath;
        }

        $response = $this->requestResource($actionPath, $params, 'DELETE', $json = false);
        return $this->processHttpResponse($response);
    }

    /**
     * Retrieves the '/access' resource of the Proxmox API resources tree.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getAccess()
    {
        return $this->get('/access');
    }


    /**
     * Retrieves the '/cluster' resource of the Proxmox API resources tree.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getCluster()
    {
        return $this->get('/cluster');
    }


    /**
     * Retrieves the '/nodes' resource of the Proxmox API resources tree.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getNodes()
    {
        return $this->get('/nodes');
    }


    /**
     * Retrieves the '/pools' resource of the Proxmox API resources tree.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getPools()
    {
        return $this->get('/pools');
    }


    /**
     * Creates a pool resource inside the '/pools' resources tree.
     *
     * @param array $poolData An associative array filled with POST params
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function createPool($poolData)
    {
        if (!is_array($poolData)) {
            throw new \InvalidArgumentException('Pool data needs to be array');
        }

        return $this->create('/pools', $poolData);
    }


    /**
     * Retrieves all the storage found in the Proxmox server, or only the ones
     * matching the storage type provided if any.
     *
     * @param string $type the storage type.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getStorages($type = null)
    {
        if ($type === null) {
            return $this->get('/storage');
        }

        $supportedTypes = array(
            'lvm',
            'nfs',
            'dir',
            'zfs',
            'rbd',
            'iscsi',
            'sheepdog',
            'glusterfs',
            'iscsidirect',
        );

        if (in_array($type, $supportedTypes)) {
            return $this->get('/storage', array(
                'type' => $type,
            ));
        }

        /* If type not found returns null */
        return null;
    }


    /**
     * Creates a storage resource using the passed data.
     *
     * @param array $storageData An associative array filled with POST params
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function createStorage($storageData)
    {
        if (!is_array($storageData)) {
            $errorMessage = 'Storage data needs to be array';
            throw new \InvalidArgumentException($errorMessage);
        }

        /* Should we check the required keys (storage, type) in the array? */

        return $this->create('/storage', $storageData);
    }


    /**
     * Retrieves the '/version' resource of the Proxmox API resources tree.
     *
     * @return mixed The processed response, can be an array, string or object.
     */
    public function getVersion()
    {
        return $this->get('/version');
    }
}
