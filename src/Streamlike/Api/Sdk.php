<?php

namespace Streamlike\Api;

if (!class_exists('Streamlike\Api\Exception\Exception')) {
    require_once __DIR__.'/Exception/Exception.php';
    require_once __DIR__.'/Exception/AuthException.php';
    require_once __DIR__.'/Exception/AuthRequiredException.php';
    require_once __DIR__.'/Exception/InvalidInputException.php';
}

use Streamlike\Api\Exception\Exception;
use Streamlike\Api\Exception\AuthException;
use Streamlike\Api\Exception\AuthRequiredException;
use Streamlike\Api\Exception\InvalidInputException;

class Sdk
{
    /**
     * HTTP Separator for multipart request.
     */
    const BOUNDARY = 'STREAMLIKEBOUND';

    /**
     * Authentication with a single-use token in order to obtain a session token.
     */
    const AUTH_REQUEST_TOKEN = 0;

    /**
     * Authentication with a certificate in order to obtain a single-use token.
     */
    const AUTH_REQUEST_CA = 1;

    private $token;
    private $host = 'https://api.streamlike.com/';
    private $isAuthenticating = false;

    /**
     * @param string $token The API token
     */
    public function __construct($token = null)
    {
        if (null !== $token && !is_string($token)) {
            throw new \InvalidArgumentException('Invalid token');
        }

        $this->token = $token;
    }

    /**
     * @return null|string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $host
     *
     * @return Sdk
     */
    public function setHost($host)
    {
        if (false === filter_var($host, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid host provided');
        } elseif ('/' !== substr($host, -1)) {
            $host .= '/';
        }

        $this->host = $host;

        return $this;
    }

    /**
     * API authentication.
     *
     * @param string $login    A login
     * @param string $password A password
     *
     * @return string token if successful authentication
     *
     * @throws \Exception
     */
    public function authenticate($login, $password)
    {
        if (null !== $this->token) {
            throw new \LogicException('SDK is already authenticated');
        }

        // get unique token
        try {
            $this->isAuthenticating = true;
            $result = $this->call('authent/token/unique', 'POST', [
                'login' => $login,
                'password' => $password,
            ]);

            $this->isAuthenticating = false;
            $this->token = $result['token'];
        } catch (\Exception $e) {
            $this->isAuthenticating = false;
            throw $e;
        }

        // get session token
        $result = $this->call('authent/token/session', 'POST');

        $this->token = $result['token'];

        return $result;
    }

    /**
     * API call.
     *
     * @param string $path  An API module
     * @param string $verb  Request type: POST, PATCH, GET, DELETE
     * @param array  $args  An array of arguments
     * @param array  $files An array of files [['
     *
     * @return array|bool true on empty result or an array of data
     *
     * @throws Exception
     */
    public function call($path, $verb = 'GET', array $args = [], array $files = [])
    {
        $verb = strtoupper($verb);

        if (!$this->isAuthenticating && empty($this->token)) {
            throw new AuthException('No token');
        } elseif (!in_array($verb, ['POST', 'PATCH', 'GET', 'DELETE'])) {
            throw new \InvalidArgumentException('Invalid HTTP method');
        } elseif (!in_array(strtoupper($verb), ['POST', 'PATCH']) && !empty($files)) {
            throw new \InvalidArgumentException('Files can only be uploaded with POST or PATCH method');
        }

        list($httpStatus, $response) = $this->doCall($path, $verb, $args, $files);

        return $this->parseResponse($httpStatus, $response);
    }

    /**
     * Initialize a cURL handle.
     *
     * @param string $path  An API module
     * @param string $verb  Request type: POST, PUT, GET, DELETE
     * @param array  $args  An array of arguments
     * @param array  $files List of file to send in multipart
     *
     * @return array The http status code, and the response string
     *
     * @throws Exception
     */
    private function doCall($path, $verb, array $args, array $files)
    {
        $options = $this->prepareRequest($path, $verb, $args, $files);

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (false === $response) {
            $e = new Exception(curl_error($ch), curl_errno($ch));
            curl_close($ch);
            throw $e;
        }

        curl_close($ch);

        return [
            $httpStatus,
            $response,
        ];
    }

    /**
     * @throws Exception
     */
    private function prepareRequest($path, $verb, array $args, array $files)
    {
        if ($isMultipart = !empty($files)) {
            $verb = 'POST';
        }
        $hasPayload = 'POST' === $verb || 'PATCH' === $verb;

        $headers = [
            'Accept: application/json',
        ];
        if (null !== $this->token) {
            $headers[] = 'X-Streamlike-Authorization: streamlikeAuth token="'.$this->token.'"';
        }

        if ($isMultipart) {
            $headers[] = 'Content-Type: multipart/form-data; boundary="'.self::BOUNDARY.'"';
        } else {
            $headers[] = 'Content-type: application/json';
        }

        $options = [
            CURLOPT_URL => $this->host.$path,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
        ];

        if ('POST' === $verb) {
            $options[CURLOPT_POST] = true;
        } elseif ('PATCH' === $verb) {
            $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        } elseif ('DELETE' == $verb) {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        } else { // GET
            if (count($args) > 0) {
                $options[CURLOPT_URL] .= (false === strpos($options[CURLOPT_URL], '?') ? '?' : '').http_build_query($args);
            }
        }

        if ($hasPayload) {
            $options[CURLOPT_POSTFIELDS] = self::buildPayload($args, $files);
        }

        return $options;
    }

    /**
     * @param array $args
     * @param array $files
     *
     * @return string
     *
     * @throws Exception
     */
    private static function buildPayload(array $args, array $files)
    {
        $payload = json_encode($args);

        if (!empty($files)) {
            $payload = '
--'.self::BOUNDARY.'
Content-Type: application/json; charset=UTF-8
Content-Disposition: form-data; name="resource"

'.$payload.'

--'.self::BOUNDARY;
            $flattenedFiles = self::arrayToForm($files);

            foreach ($flattenedFiles as $inputName => $filePath) {
                if (!file_exists($filePath) || false === ($fileData = file_get_contents($filePath))) {
                    throw new Exception('Multipart: could not download file.');
                }

                $fileName = pathinfo($filePath, PATHINFO_BASENAME);

                $payload .=
'
Content-Transfer-Encoding: binary
Content-Disposition: form-data; name="'.$inputName.'"; filename="'.$fileName.'"

'.$fileData.'
--'.self::BOUNDARY;
            }

            $payload .= '--';
        }

        return $payload;
    }

    /**
     * @param int    $httpStatus
     * @param string $response
     *
     * @return bool|mixed
     *
     * @throws AuthRequiredException
     * @throws Exception
     * @throws InvalidInputException
     */
    private static function parseResponse($httpStatus, $response)
    {
        if (401 === $httpStatus || 403 === $httpStatus) {
            throw new AuthRequiredException($httpStatus, $httpStatus);
        } elseif (400 === $httpStatus) { // invalid input
            $result = json_decode($response, true);

            throw new InvalidInputException(
                is_array($result) && array_key_exists('message', $result) ? $result['message'] : $httpStatus,
                isset($result['data']['errors']) ? $result['data']['errors'] : []
            );
        } elseif ($httpStatus >= 400 && $httpStatus < 500) {
            throw new Exception('Invalid request', $httpStatus);
        } elseif ($httpStatus >= 500) {
            throw new Exception('Server error', $httpStatus);
        } elseif (204 === $httpStatus) { // empty response
            return true;
        }

        $data = json_decode($response, true);

        if (null === $data) {
            throw new Exception("Error while decoding response, original response:\n".print_r($response, true));
        }

        return $data;
    }

    // ------ TOOLS ------ \\

    private static function arrayToForm(array $values, array &$result = [], $previous = null)
    {
        foreach ($values as $key => $value) {
            $currentKey = null === $previous ? $key : $previous.'['.$key.']';
            if (is_array($value)) {
                self::arrayToForm($value, $result, $currentKey);
            } else {
                $result[$currentKey] = $value;
            }
        }

        return $result;
    }
}
