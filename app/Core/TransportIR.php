<?php
namespace PentagonalProject\Client17Ir\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use function PentagonalProject\Client17Ir\who;
use Psr\Http\Message\ResponseInterface;
use Wa72\HtmlPageDom\HtmlPageCrawler;

/**
 * Class TransportIR
 * @package PentagonalProject\Client17Ir\Core
 */
class TransportIR
{
    const
    BASE_URI    = 'http://www.nic.ir/Just_Released',
    IMAGE_URI   = 'http://www.nic.ir/Show_CAPTCHA',
    RELEASE_URI = 'http://www.nic.ir/Just_Released?captcha=',
    UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36';

    const CAPTCHA_IN = 'http://2captcha.com/in.php';
    const CAPTCHA_RES = 'http://2captcha.com/res.php';
    const WAIT_TIME = 6;
    public $cacheTime = 86400;

    /**
     * @var string
     */
    protected $data;

    /**
     * @var Client
     */
    protected $clientIR;

    /**
     * @var Client
     */
    protected $clientCaptcha;

    /**
     * @var string
     */
    protected $captchaImage;

    /**
     * @var string
     */
    protected $captchaId;

    /**
     * @var string
     */
    protected $captchaResolved;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var bool
     */
    public $cliVerbose = false;

    /**
     * TransportIR constructor.
     *
     * @param $key
     */
    public function __construct($key)
    {
        $this->apiKey = $key;
        $this->clientIR = new Client([
            'cookies' => true,
            'headers' => [
                'Referer' => self::BASE_URI,
                'User-Agent' => self::UA
            ]
        ]);
        $this->clientCaptcha = new Client([
            'headers' => [
                'User-Agent' => self::UA
            ]
        ]);
    }

    /**
     * @param $message
     * @param bool $withNewLine
     */
    public function addVerbose($message, $withNewLine = true)
    {
        if ($this->cliVerbose && php_sapi_name() == 'cli') {
            echo "{$message}";
            if ($withNewLine) {
                echo "\n";
            }
        }
    }

    protected function startData()
    {
        if (!empty($this->captchaImage)) {
            return;
        }

        $this->addVerbose("Requesting Captcha from whois ir.");
        $client = clone $this->clientIR;
        try {
            $response = $client->get(self::IMAGE_URI);
            if ($response instanceof \Exception) {
                throw $response;
            }
        } catch (\Exception $e) {
            if (preg_match('/timed?\s+out/i', $e->getMessage())) {
                $this->addVerbose("Connection timeout. Retrying ....");
                $response = $client->get(self::IMAGE_URI);
            } else {
                throw $e;
            }
        }

        if ($response instanceof ResponseInterface) {
            $this->captchaImage = '';
            $body = $response->getBody();
            while (!$body->eof()) {
                $this->captchaImage .= $body->getContents();
            }
            $this->addVerbose("Requesting captcha from whois ir has succeed.");
            return;
        }
        if ($response instanceof \Exception) {
            throw  $response;
        }
        throw new \Exception('Can Not Get Data');
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getResponseCaptchaID()
    {
        if (isset($this->captchaId)) {
            return $this->captchaId;
        }

        $this->startData();
        $responseCall = function () {
            return $this
                ->clientCaptcha
                ->post(self::CAPTCHA_IN, [
                    'form_params' => [
                        'method' => 'base64',
                        'key' => $this->apiKey,
                        'phrase' => '0',
                        'min_len' => '4',
                        'json' => '1',
                        'soft_id' => '4499141',
                        'body' => base64_encode($this->captchaImage)
                    ]
                ]);
        };
        try {
            $this->addVerbose("Posting captcha data to Captcha Resolver API.");
            $response = $responseCall();
            if ($response instanceof \Exception) {
                throw $response;
            }
        } catch (\Exception $e) {
            if (preg_match('/timed?\s+out/i', $e->getMessage())) {
                $this->addVerbose("Posting captcha timed out. Retrying...");
                $response = $responseCall();
            } else {
                throw $e;
            }
        }
        if ($response instanceof ResponseInterface) {
            $data = '';
            $body = $response->getBody();
            while (!$body->eof()) {
                $data .= $body->getContents();
            }
            $data = json_decode($data, true);
            if (!is_array($data) || empty($data['request'])) {
                throw new \Exception('Can Not Get Data From POST 2Captcha');
            }
            if (stripos($data['request'], '_BALANCE') !== false) {
                throw new \Exception("No Credit On Service! Please update Balance");
            }
            if (substr_count($data['request'], '_') > 1) {
                throw new \Exception(
                    'There was error when Get Data From POST 2Captcha with Result: '. $data['request']
                );
            }

            $this->captchaId = $data['request'];
            $this->addVerbose("Posting captcha succeed with id: ". $this->captchaId);
            return $this->captchaId;
        }

        if ($response instanceof \Exception) {
            throw  $response;
        }

        throw new \Exception('Can Not Get Data From POST 2Captcha');
    }

    const NOT_RESOLVED = 'not resolved';
    const ERROR_CAPTCHA = 'error captcha';
    const FAIL_CAPTCHA  = 'fail captcha';
    const ZERO_BALANCE  = 'No Balance';

    /**
     * @param string $id
     *
     * @return string
     */
    protected function getCaptcha($id)
    {
        if (!empty($this->captchaResolved)) {
            return $this->captchaResolved;
        }

        $uri = self::CAPTCHA_RES
               . '?key=' . $this->apiKey
               . '&action=get'
               . '&id=' . $id
               . '&json=1';
        $response = $this
            ->clientCaptcha
            ->get($uri);
        if (!$response instanceof ResponseInterface) {
            return self::FAIL_CAPTCHA;
        }
        $data = '';
        $body = $response->getBody();
        while (!$body->eof()) {
            $data .= $body->getContents();
        }
        $this->addVerbose("Data Received : {$data}");
        $data = json_decode($data, true);
        if (!is_array($data) || empty($data['request'])) {
            return self::FAIL_CAPTCHA;
        }

        $data = @trim($data['request']);
        if (stripos($data, '_NOT_READY') !== false) {
            return self::NOT_RESOLVED;
        }

        if (stripos($data, '_BALANCE') !== false) {
            return self::ZERO_BALANCE;
        }

        if (strpos($data, '_') !== false) {
            return self::ERROR_CAPTCHA;
        }
        $this->addVerbose("Captcha found : {$data}");
        return $data;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    protected function requestGetCaptcha()
    {
        if (!empty($this->captchaResolved)) {
            return $this->captchaResolved;
        }

        $id = $this->getResponseCaptchaID();
        $this->addVerbose("Get resolved captcha from Captcha Resolver API with id: ".$id);
        $counted = 0;
        $resolved = null;
        while ($counted < 20) {
            $resolved = $this->getCaptcha($id);
            $counted++;
            if ($resolved == self::FAIL_CAPTCHA || $resolved == self::NOT_RESOLVED) {
                if ($resolved == self::NOT_RESOLVED) {
                    $this->addVerbose(
                        "Retrying to call captcha resolve with ID : "
                        . $id
                        . '. Waiting for ' .self::WAIT_TIME. ' seconds'
                    );
                } else {
                    $this->addVerbose(
                        "Fail to get captcha resolve, Waiting for ".self::WAIT_TIME." seconds. Retrying..."
                    );
                }
                sleep(self::WAIT_TIME);
                continue;
            } elseif ($resolved == self::ZERO_BALANCE) {
                throw new \Exception("No Credit On Service! Please update Balance");
            } elseif ($resolved == self::ERROR_CAPTCHA) {
                throw new \Exception("Can Not get Data Captcha! De-parsing FAILED!");
            }
            break;
        }

        if ($resolved == self::FAIL_CAPTCHA || $resolved == self::NOT_RESOLVED || $resolved == self::ERROR_CAPTCHA) {
            return null;
        }

        $this->captchaResolved = $resolved;
        return $this->captchaResolved;
    }

    /**
     * @param string $captcha
     *
     * @return string
     */
    protected function requestDataFromIr($captcha)
    {
        $this->addVerbose("Requesting domain list with existing captcha....");
        $response = $this
            ->clientIR
            ->get(self::BASE_URI . '?captcha='.$captcha);

        if (!$response instanceof ResponseInterface) {
            throw $response;
        }

        $data = '';
        $body = $response->getBody();
        while (!$body->eof()) {
            $data .= $body->getContents();
        }

        return $data;
    }

    /**
     * @param string $stringData
     *
     * @return array
     */
    private function parse($stringData)
    {
        $key = sha1($stringData);
        $error_report = error_reporting();
        error_reporting(0);

        /**
         * @var Cache $cache
         */
        $cache = DI::get(Cache::class);
        if ($cache->exist($key)) {
            $data = $cache->get($key);
            if (is_array($data)) {
                return $data;
            }
            $cache->delete($key);
        }

        $this->addVerbose("Parsing result!");
        $data = [];
        $crawl = HtmlPageCrawler::create($stringData);
        $crawl = $crawl->filter('.listing-table');
        if ($crawl->count() < 2) {
            $this->addVerbose("Result is empty!");
            return $data;
        }

        $crawl = $crawl->eq(1)->filter('tr[class]');
        if ($crawl->count() < 10) {
            $this->addVerbose("Result is empty!");
            return $data;
        }

        $crawl->each(function (HtmlPageCrawler $crawler) use (&$data) {
            $td = $crawler->filter('td.primary-cell');
            if ($td->count() < 2) {
                return;
            }
            $tdOne     = $td->eq(0)->nextAll();
            $domainOne = $tdOne->filter('tt');
            if ($domainOne->count()) {
                $domainOne = strtolower(trim($domainOne->html()));
                $dateOld =  trim($tdOne->nextAll()->first()->text());
                $dateOld = $dateOld ? preg_replace(
                    [
                        '/([\-])\s+/',
                        '/(\s)+/'
                    ],
                    '$1',
                    $dateOld
                ) : $dateOld;
                $date = @strtotime($dateOld);
                $date = $date?: $dateOld;
                $domainOne = html_entity_decode($domainOne);
                if (!who()->getVerifier()->isDomain($domainOne)) {
                    return;
                }

                $data[$domainOne] = @date('Y-m-d H:i:s', $date)?: date('Y-m-d H:i:s');
            }

            $tdTwo     = $td->eq(1)->nextAll();
            $domainTwo = $tdTwo->filter('tt');
            if ($domainTwo->count()) {
                $domainTwo = strtolower(trim($domainTwo->html()));
                $dateOld =  trim($tdTwo->nextAll()->first()->text());
                $dateOld = $dateOld ? preg_replace(
                    [
                        '/([\-])\s+/',
                        '/(\s)+/'
                    ],
                    '$1',
                    $dateOld
                ) : $dateOld;
                $date = @strtotime($dateOld);
                $date = $date?: $dateOld;
                $domainTwo = html_entity_decode($domainTwo);
                if (!who()->getVerifier()->isDomain($domainTwo)) {
                    return;
                }
                $data[$domainTwo] = @date('Y-m-d H:i:s', $date) ?: date('Y-m-d H:i:s');
            }
        });
        if (!empty($data)) {
            $cache->put($key, $data, 3600*24*7);
        }
        // set error report
        error_reporting($error_report);
        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getWebPageDomainList()
    {
        try {
            $key = sha1(self::BASE_URI);
            /**
             * @var Cache $cache
             */
            $cache = DI::get(Cache::class);
            if ($cache->exist($key)) {
                $data = $cache->get($key);
                if (is_string($data)) {
                    return $this->parse($data);
                }
                $cache->delete($key);
            }

            $resolved = $this->requestGetCaptcha();
            if ($resolved === null || trim($resolved) == '') {
                throw new \Exception('Can Not Get Data');
            }

            $data = $this->requestDataFromIr(trim($resolved));
            // temp
            $this->addVerbose("Requesting domain list with existing succeed!");
            $result = $this->parse($data);
            if (!empty($result)) {
                if (!is_int($this->cacheTime)) {
                    $this->cacheTime = 86400;
                }
                $cache->put($key, $data, $this->cacheTime);
            }
            return $result;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param string $domainName
     *
     * @return array|null
     */
    public function getAlexa($domainName)
    {
        if (!is_string($domainName) || trim($domainName) == '') {
            return null;
        }
        $domainName = trim(strtolower($domainName));
        /**
         * @var Cache $cache
         */
        $cache = DI::get(Cache::class);
        $this->addVerbose("Checking Alexa Rank: {$domainName}");
        $uri = 'https://www.alexa.com/siteinfo/' . $domainName;
        $cacheKey = sha1($uri);
        $data = $cache->get($cacheKey);
        $isCache = false;
        if (!is_string($data) || trim($data) == '') {
            $client =  clone $this->clientIR;
            $config = $client->getConfig();
            $config['headers']['referer'] = 'https://www.alexa.com/';
            try {
                $response = $client->get($uri, $config);
            } catch (\Exception $e) {
                if (preg_match('/timed?\s+out/i', $e->getMessage())) {
                    $this->addVerbose("Alexa Rank check time out. Retrying...");
                    try {
                        $response = $client->get($uri, $config);
                    } catch (\Exception $e) {
                        if ($e instanceof BadResponseException) {
                            if ((int) $e->getResponse()->getStatusCode() === 403) {
                                $this->addVerbose("Skipped Forbidden Result for: [ {$domainName} ]");
                                return -1;
                            }
                        }
                    }
                }

                if ($e instanceof BadResponseException) {
                    if ((int) $e->getResponse()->getStatusCode() === 403) {
                        $this->addVerbose("Skipped Forbidden Result for: [ {$domainName} ]");
                        return -1;
                    }
                }
            }

            if ( ! isset($response) || ! $response instanceof ResponseInterface) {
                $this->addVerbose("Skipped : [ {$domainName} ]");
                return false;
            }

            $data = '';
            $body = $response->getBody();
            while ( ! $body->eof()) {
                $data .= $body->getContents();
            }
            // add cache
            $cache->put($cacheKey, $data, 3600*24);
        } else {
            $isCache = true;
        }

        $data   = HtmlPageCrawler::create($data);
        $metric = $data->filter('.metrics-data');
        if ($metric->count() < 1) {
            unset($data, $metric);
            return null;
        }

        $rank = trim($metric->first()->text());
        $rank = $rank == '-' ? null : abs(preg_replace('/[^0-9]/', '', $rank));
        $rank = $rank ? $rank : ($rank === null ? null : 0);

        return [
            'cache' => $isCache,
            'result' => $rank
        ];
    }

    public function getGoogleBackLink($domainName)
    {
        if (!is_string($domainName)) {
            return null;
        }
        $domainName = trim(strtolower($domainName));
        /**
         * @var Cache $cache
         */
        $cache = DI::get(Cache::class);
        $query = urlencode('backlink:'.$domainName);
        $uri = 'https://www.google.com/search?q='.$query.'&num=100';
        $cacheKey = sha1($uri);
        $data = $cache->get($cacheKey);
        $isCache = false;
        if (!is_string($data) || trim($data) == '') {
            $client =  clone $this->clientIR;
            $config = $client->getConfig();
            $config['headers']['referer'] = 'https://www.google.com/?gws_rd=cr&dcr=0';
            try {
                $response = $client->get($uri, $config);
            } catch (\Exception $e) {
                if (preg_match('/timed?\s+out/i', $e->getMessage())) {
                    $this->addVerbose("Google Backlink Check time out. Retrying...");
                    try {
                        $response = $client->get($uri, $config);
                    } catch (\Exception $e) {
                        if ($e instanceof BadResponseException) {
                            $body = (string) $e->getResponse()->getBody();
                            if (stripos($body, 'Our systems have detected unusual') !== false
                                || stripos($body, 'id="recaptcha"') !== false
                            ) {
                                $this->addVerbose("Skipped Unwanted Result From Google for: {$domainName}");
                                return -1;
                            }
                        }
                    }
                } else {
                    if ($e instanceof BadResponseException) {
                        $body = (string) $e->getResponse()->getBody();
                        if (stripos($body, 'Our systems have detected unusual') !== false
                            || stripos($body, 'id="recaptcha"') !== false
                        ) {
                            $this->addVerbose("Skipped Unwanted Result From Google for: [ {$domainName} ]");
                            return -1;
                        }
                    }
                }
            }

            if ( ! isset($response) || ! $response instanceof ResponseInterface) {
                $this->addVerbose("Skipped : [ {$domainName} ]");
                return false;
            }

            $data = '';
            $body = $response->getBody();
            while ( ! $body->eof()) {
                $data .= $body->getContents();
            }

            // add cache
            $cache->put($cacheKey, $data, 3600*24);
        } else {
            $isCache = true;
        }

        $crawler  = HtmlPageCrawler::create($data);
        $crawler  = $crawler->filter('[data-hveid]');
        if ($crawler->count() < 1) {
            return null;
        }

        $backLink = [];
        $crawler->each(function (HtmlPageCrawler $crawler) use (&$backLink) {
            $crawler = $crawler->filter('h3');
            if ($crawler->count() < 1) {
                return;
            }
            $crawler = $crawler->first();
            if ($crawler->count() < 1) {
                return;
            }
            $crawler = $crawler->filter('a[href]');
            if ($crawler->count() < 1) {
                return;
            }

            /**
             * @var HtmlPageCrawler $crawler
             */
            try {
                $href = $crawler->getAttribute('href');
                if ($href && is_string($href)) {
                    $backLink[] = $href;
                }
            } catch (\Exception $e) {
                // pass
            }
        });

        return [
            'cache' => $isCache,
            'result' => $backLink
        ];
    }
}
