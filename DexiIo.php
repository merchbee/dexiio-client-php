<?php

class DexiIo
{

    /**
     * @var DexiIoClient
     */
    private static $client;


    public static function init($apiKey, $accountId)
    {
        self::$client = new DexiIoClient($apiKey, $accountId);
    }

    /**
     * @return DexiIoClient
     * @throws Exception if DexiIo::init was not called
     */
    public static function defaultClient()
    {
        self::checkState();

        return self::$client;
    }

    /**
     * @return DexiIoExecutions
     * @throws Exception if DexiIo::init was not called
     */
    public static function executions()
    {
        self::checkState();

        return self::$client->executions();
    }

    /**
     * @return DexiIoRuns
     * @throws Exception if DexiIo::init was not called
     */
    public static function runs()
    {
        self::checkState();

        return self::$client->runs();
    }

    private static function checkState()
    {
        if (!self::$client) {
            throw new Exception('You must call init first before using the API');
        }
    }
}

class DexiIoClient
{

    private $endPoint = 'https://app.dexi.io/api/';
    private $userAgent = 'DI-PHP-CLIENT/1.0';
    private $apiKey;
    private $accountId;
    private $accessKey;

    private $requestTimeout = 3600;

    /**
     * @var DexiIoExecutions
     */
    private $executions;

    /**
     * @var DexiIoRuns
     */
    private $runs;

    function __construct($apiKey, $accountId)
    {
        $this->apiKey = $apiKey;
        $this->accountId = $accountId;
        $this->accessKey = md5($accountId . $apiKey);

        $this->executions = new DexiIoExecutions($this);
        $this->runs = new DexiIoRuns($this);
    }

    /**
     * Get current request timeout
     *
     * @return int
     */
    public function getRequestTimeout()
    {
        return $this->requestTimeout;
    }

    /**
     * Set request timeout. Defaults to 1 hour.
     *
     * Note: If you are using the sync methods and some requests are running for very long you need to increase this value.
     *
     * @param int $requestTimeout
     */
    public function setRequestTimeout($requestTimeout)
    {
        $this->requestTimeout = $requestTimeout;
    }


    /**
     * Get endpoint / base url of requests
     *
     * @return string
     */
    public function getEndPoint()
    {
        return $this->endPoint;
    }

    /**
     * Set end point / base url of requests
     *
     * @param string $endPoint
     */
    public function setEndPoint($endPoint)
    {
        $this->endPoint = $endPoint;
    }

    /**
     * Get user agent of requests
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Set user agent of requests
     *
     * @param string $userAgent
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }


    /**
     *
     * Make a call to the DexiIo API
     *
     * @param string $url
     * @param string $method
     * @param mixed  $body Will be converted into json
     *
     * @return object
     * @throws DexiIoRequestException
     */
    public function request($url, $method = 'GET', $body = null)
    {
        $content = $body ? json_encode($body) : null;

        $headers = array();
        $headers[] = "X-DexiIO-Access: $this->accessKey";
        $headers[] = "X-DexiIO-Account: $this->accountId";
        $headers[] = "User-Agent: $this->userAgent";
        $headers[] = "Accept: application/json";
        $headers[] = "Content-Type: application/json";

        if ($content) {
            $headers[] = "Content-Length: " . strlen($content);
        }

        $requestDetails = array(
            'method' => $method,
            'header' => join("\r\n", $headers),
            'content' => $content,
            'timeout' => $this->requestTimeout
        );

        $context = stream_context_create(array(
            'https' => $requestDetails,
            'http' => $requestDetails
        ));

        $outRaw = @file_get_contents($this->endPoint . $url, false, $context);

        $out = $this->parseHeaders($http_response_header);

        $out->content = $outRaw;

        if ($out->statusCode < 100 || $out->statusCode > 399) {
            throw new DexiIoRequestException("DexiIo request failed: $out->statusCode $out->reason", $url, $out);
        }

        return $out;
    }

    /**
     * @param string $url
     * @param string $method
     * @param mixed  $body
     *
     * @return mixed
     * @throws DexiIoRequestException
     */
    public function requestJson($url, $method = 'GET', $body = null)
    {
        $response = $this->request($url, $method, $body);
        return json_decode($response->content);
    }

    /**
     * @param string $url
     * @param string $method
     * @param mixed  $body
     *
     * @return bool
     * @throws DexiIoRequestException
     */
    public function requestBoolean($url, $method = 'GET', $body = null)
    {
        $this->request($url, $method, $body);
        return true;
    }

    private function parseHeaders($http_response_header)
    {
        $status = 0;
        $reason = '';
        $outHeaders = array();

        if ($http_response_header &&
            count($http_response_header) > 0
        ) {
            $httpHeader = array_shift($http_response_header);
            if (preg_match('/([0-9]{3})\s+([A-Z_]+)/i', $httpHeader, $matches)) {
                $status = intval($matches[1]);
                $reason = $matches[2];
            }

            foreach ($http_response_header as $header) {
                $parts = explode(':', $header, 2);
                if (count($parts) < 2) {
                    continue;
                }

                $outHeaders[trim($parts[0])] = $parts[1];
            }
        }

        return (object)array(
            'statusCode' => $status,
            'reason' => $reason,
            'headers' => $outHeaders
        );
    }

    /**
     * Interact with executions.
     *
     * @return DexiIoExecutions
     */
    public function executions()
    {
        return $this->executions;
    }

    /**
     * Interact with runs
     *
     * @return DexiIoRuns
     */
    public function runs()
    {
        return $this->runs;
    }

}

class DexiIoExecutions
{

    /**
     * @var DexiIoClient
     */
    private $client;

    function __construct(DexiIoClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get execution
     *
     * @param string $executionId
     *
     * @return DexiIoExecutionDTO
     */
    public function get($executionId)
    {
        return $this->client->requestJson("executions/$executionId");
    }

    /**
     * Delete execution permanently
     *
     * @param string $executionId
     *
     * @return boolean
     */
    public function remove($executionId)
    {
        return $this->client->requestBoolean("executions/$executionId", 'DELETE');
    }

    /**
     * Get the entire result of an execution.
     *
     * @param string $executionId
     *
     * @return DexiIoResultDTO
     */
    public function getResult($executionId)
    {
        return $this->client->requestJson("executions/$executionId/result");
    }

    /**
     * Get a file from a result set
     *
     * @param string $executionId
     * @param string $fileId
     *
     * @return DexiIoFileDTO
     */
    public function getResultFile($executionId, $fileId)
    {
        $response = $this->client->request("executions/$executionId/file/$fileId");
        return new DexiIoFileDTO($response->headers['Content-Type'], $response->content);
    }

    /**
     * Stop running execution
     *
     * @param string $executionId
     *
     * @return bool
     */
    public function stop($executionId)
    {
        return $this->client->requestBoolean("executions/$executionId/stop", 'POST');
    }

    /**
     * Resume stopped execution
     *
     * @param string $executionId
     *
     * @return bool
     */
    public function resume($executionId)
    {
        return $this->client->requestBoolean("executions/$executionId/continue", 'POST');
    }
}

class DexiIoRuns
{

    /**
     * @var DexiIoClient
     */
    private $client;

    function __construct(DexiIoClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $runId
     *
     * @return DexiIoRunDTO
     */
    public function get($runId)
    {
        return $this->client->requestJson("runs/$runId");
    }

    /**
     * Permanently delete run
     *
     * @param string $runId
     *
     * @return bool
     */
    public function remove($runId)
    {
        return $this->client->requestBoolean("runs/$runId", 'DELETE');
    }

    /**
     * Start new execution of the run
     *
     * @param string $runId
     *
     * @return DexiIoExecutionDTO
     */
    public function execute($runId)
    {
        return $this->client->requestJson("runs/$runId/execute", 'POST');
    }

    /**
     * Start new execution of the run, and wait for it to finish before returning the result.
     * The execution and result will be automatically deleted from DexiIo completion
     * - both successful and failed.
     *
     * @param string $runId
     *
     * @return DexiIoResultDTO
     */
    public function executeSync($runId)
    {
        return $this->client->requestJson("runs/$runId/execute/wait", 'POST');
    }

    /**
     * Starts new execution of run with given inputs
     *
     * @param string $runId
     * @param object $inputs
     *
     * @return DexiIoExecutionDTO
     */
    public function executeWithInput($runId, $inputs)
    {
        return $this->client->requestJson("runs/$runId/execute/inputs", 'POST', $inputs);
    }

    /**
     * Starts new execution of run with given inputs, and wait for it to finish before returning the result.
     * The inputs, execution and result will be automatically deleted from DexiIo upon completion
     * - both successful and failed.
     *
     * @param string $runId
     * @param array  $inputs array of input objects
     *
     * @return DexiIoExecutionDTO
     */
    public function executeBulkSync($runId, $inputs)
    {
        return $this->client->requestJson("runs/$runId/execute/bulk/wait", 'POST', $inputs);
    }

    /**
     * Starts new execution of run with given inputs
     *
     * @param string $runId
     * @param object $inputs
     *
     * @return DexiIoExecutionDTO
     */
    public function executeBulk($runId, $inputs)
    {
        return $this->client->requestJson("runs/$runId/execute/bulk", 'POST', $inputs);
    }

    /**
     * Starts new execution of run with given inputs, and wait for it to finish before returning the result.
     * The inputs, execution and result will be automatically deleted from DexiIo upon completion
     * - both successful and failed.
     *
     * @param string       $runId
     * @param object|array $inputs
     *
     * @return DexiIoExecutionDTO
     */
    public function executeWithInputSync($runId, $inputs)
    {
        return $this->client->requestJson("runs/$runId/execute/inputs/wait", 'POST', $inputs);
    }

    /**
     * Get the result from the latest execution of the given run.
     *
     * @param string $runId
     *
     * @return DexiIoResultDTO
     */
    public function getLatestResult($runId)
    {
        return $this->client->requestJson("runs/$runId/latest/result");
    }

    /**
     * Get executions for the given run.
     *
     * @param string $runId
     * @param int    $offset
     * @param int    $limit
     *
     * @return DexiIoExecutionListDTO
     */
    public function getExecutions($runId, $offset = 0, $limit = 30)
    {
        return $this->client->requestJson("runs/$runId/executions?offset=$offset&limit=$limit");
    }
}

class DexiIoExecutionDTO
{
    const QUEUED = 'QUEUED';
    const PENDING = 'PENDING';
    const RUNNING = 'RUNNING';
    const FAILED = 'FAILED';
    const STOPPED = 'STOPPED';
    const OK = 'OK';

    /**
     * The ID of the execution
     *
     * @var string
     */
    public $_id;

    /**
     * State of the executions. See const definitions on class to see options
     *
     * @var string
     */
    public $_state;

    /**
     * Time the executions was started - in milliseconds since unix epoch
     *
     * @var int
     */
    public $_starts;

    /**
     * Time the executions finished - in milliseconds since unix epoch.
     * Null if execution has not yet finished.
     *
     * @var int
     */
    public $_finished;

}

class DexiIoExecutionListDTO
{
    /**
     * @var int
     */
    public $offset;

    /**
     * @var int
     */
    public $totalRows;

    /**
     * @var DexiIoExecutionDTO[]
     */
    public $rows;
}

class DexiIoResultDTO
{
    /**
     * Header fields
     *
     * @var string[]
     */
    public $headers;

    /**
     * An array of arrays containing each row - with each value inside it.
     *
     * @var mixed[][]
     */
    public $rows;

    /**
     * Total number of rows available
     *
     * @var int
     */
    public $totalRows;
}

class DexiIoFileDTO
{
    /**
     * The type of file
     *
     * @var string
     */
    public $mimeType;

    /**
     * The contents of the file
     *
     * @var string
     */
    public $contents;

    function __construct($mimeType, $contents)
    {
        $this->mimeType = $mimeType;
        $this->contents = $contents;
    }


}

class DexiIoRunDTO
{
    /**
     * The ID of the run
     *
     * @var string
     */
    public $_id;

    /**
     * Name of the run
     *
     * @var string
     */
    public $name;
}

class DexiIoRequestException extends Exception
{
    private $response;
    private $url;

    /**
     * @param string $msg
     * @param string $url
     * @param object $response
     */
    function __construct($msg, $url, $response)
    {
        parent::__construct($msg, $response->statusCode);
        $this->response = $response;
        $this->url = $url;
    }

    /**
     * @return object The response object
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * The URL of the request
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

}
