<?php
/**
 * Jano Ticketing System
 * Copyright (C) 2016-2018 Andrew Ying
 *
 * This file is part of Jano Ticketing System.
 *
 * Jano Ticketing System is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v3.0 as
 * published by the Free Software Foundation. You must preserve all legal
 * notices and author attributions present.
 *
 * Jano Ticketing System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Jano\Modules\Queue\Helpers;

use Illuminate\Config\Repository as Config;
use Illuminate\Cookie\CookieJar as Cookie;
use Illuminate\Http\Request;
use Jano\Modules\Queue\Models\QueueParams;
use Symfony\Component\Config\Definition\Exception\Exception;

class QueueHelper
{
    private const SDK_VERSION = '3.5.1';
    private const QUEUEIT_DATA_KEY = 'QueueITAccepted-SDFrts345E-V3';

    /**
     * @var array
     */
    private $config;

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var \Illuminate\Contracts\Cookie\Factory
     */
    private $cookie;

    /**
     * @var string
     */
    private $redirectUrl;

    /**
     * QueueHelper constructor.
     *
     * @param \Illuminate\Config\Repository $config
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Cookie\CookieJar $cookie
     * @throws \Symfony\Component\Config\Definition\Exception\Exception
     */
    public function __construct(Config $config, Request $request, Cookie $cookie)
    {
        if (!$config->has('queue-module.customer_id')) {
            throw new Exception('Configuration for the Queue module is invalid');
        }

        $this->config = array(
            'customer_id' => $config->get('queue-module.customer_id'),
            'secret_key' => $config->get('queue-module.secret_key'),
            'event_id' => $config->get('queue-module.event_id'),
            'queue_id' => $config->get('queue-module.queue_id'),
            'domain' => $config->get('queue-module.domain'),
            'cookie_validity' => $config->get('queue-module.cookie_validity'),
            'extend_validity' => $config->get('queue-module.extend_validity'),
            'culture' => $config->get('queue-module.culture'),
            'layout' => $config->get('queue-module.layout'),
            'version' => $config->get('queue-module.version')
        );

        $this->request = $request;
        $this->cookie = $cookie;
    }

    /**
     * Validate redirect from Queue-it.
     *
     * @param \Jano\Modules\Queue\Models\QueueParams $queueParams
     * @return bool
     */
    public function getTokenValidationResult(QueueParams $queueParams)
    {
        $eventId = $this->config['event_id'];
        $calculatedHash = hash_hmac('sha256', $queueParams->tokenWithoutHash, $this->config['secret_key']);

        if (strtoupper($calculatedHash) !== strtoupper($queueParams->hash)) {
            $this->validationError('hash');
            return false;
        }
        if (strtoupper($queueParams->eventId) !== strtoupper($eventId)) {
            $this->validationError('eventid');
            return false;
        }
        if ($queueParams->timestamp < time()) {
            $this->validationError('timestamp');
            return false;
        }

        $cookie_value = $this->createCookieValue(
            $queueParams->queueId,
            $queueParams->cookieValidityMinutes,
            $queueParams->redirectType
        );
        $this->cookie->queue($this->getCookieKey(), $cookie_value, 24 * 60);

        return true;
    }

    /**
     * @param string $errorCode
     */
    private function validationError($errorCode)
    {
        $this->redirectUrl = 'https://' . $this->config['domain'] . '/error/' . $errorCode . '/?'
            . $this->getQueryString();
    }

    /**
     * Get URL to redirect to.
     *
     * @param string|null $targetUrl
     * @return string
     */
    public function getRedirectUrl($targetUrl = null)
    {
        if (!empty($this->redirectUrl)) {
            $url = $this->redirectUrl;
        } else {
            $url = 'https://' . $this->config['domain'] . '/?';
            $url .= $this->getQueryString();
        }

        if ($targetUrl !== null) {
            $url .= '&t=' . urlencode($targetUrl);
        }

        return $url;
    }

    /**
     * Cancel queue cookie.
     */
    public function cancelQueueCookie()
    {
        $cookieKey = $this->getCookieKey();
        $this->cookie->queue($cookieKey, null);
    }

    public function reissueQueueCookie()
    {
        $cookieKey = $this->getCookieKey();
        $cookie = $this->request->cookie($cookieKey);

        if ($cookie === null) {
            return;
        }

        $cookieNameValueMap = self::getCookieNameValueMap($cookie);
        if (!$this->isCookieValid($cookieNameValueMap, true)) {
            return;
        }

        $fixedCookieValidityMinutes = '';
        if (array_key_exists('FixedValidityMins', $cookieNameValueMap)) {
            $fixedCookieValidityMinutes = $cookieNameValueMap['FixedValidityMins'];
        }

        $cookieValue = $this->createCookieValue(
            $cookieNameValueMap['QueueId'],
            $fixedCookieValidityMinutes,
            $cookieNameValueMap['RedirectType']
        );

        $this->cookie->queue($cookieKey, $cookieValue, 24 * 60);
    }

    /**
     * Generate query string for redirect to Queue-it.
     *
     * @return string
     */
    private function getQueryString()
    {
        $query = 'c=' . urlencode($this->config['customer_id'])
            . '&e=' . urlencode($this->config['event_id'])
            . '&ver=v3-php-' . self::SDK_VERSION
            . '&cver='. urlencode($this->config['version']);
        if ($this->config['culture']) {
            $query .= '&cid=' . urlencode($this->config['culture']);
        }
        if ($this->config['layout']) {
            $query .= '&l=' . urlencode($this->config['layout']);
        }

        return $query;
    }

    /**
     * Get Queue-it cookie key.
     *
     * @return string
     */
    private function getCookieKey()
    {
        return self::QUEUEIT_DATA_KEY . '_' . $this->config['event_id'];
    }

    /**
     * Create Queue-it cookie.
     *
     * @param $queueId
     * @param $fixedCookieValidityMinutes
     * @param string $redirectType
     */
    public function createCookie($queueId, $fixedCookieValidityMinutes, $redirectType)
    {
        $this->cookie->make(
            $this->getCookieKey(),
            $this->createCookieValue($queueId, $fixedCookieValidityMinutes, $redirectType),
            24 * 60
        );
    }

    /**
     * @param string $queueId
     * @param $fixedCookieValidityMinutes
     * @param string $redirectType
     * @return string
     */
    private function createCookieValue($queueId, $fixedCookieValidityMinutes, $redirectType)
    {
        $eventId = $this->config['event_id'];
        $secretKey = $this->config['secret_key'];

        $issueTime = time();
        $hashValue = hash_hmac(
            'sha256',
            $eventId . $queueId . $fixedCookieValidityMinutes . $redirectType . $issueTime,
            $secretKey
        );

        $fixedCookieValidityMinutesPart = '';
        if ($fixedCookieValidityMinutes) {
            $fixedCookieValidityMinutesPart = '&FixedValidityMins=' . $fixedCookieValidityMinutes;
        }

        $cookieValue = 'EventId=' . $eventId . '&QueueId=' . $queueId . $fixedCookieValidityMinutesPart
            . '&RedirectType=' . $redirectType . '&IssueTime=' . $issueTime . '&Hash=' . $hashValue;
        return $cookieValue;
    }

    /**
     * @param string|array $cookie
     * @return array
     */
    private static function getCookieNameValueMap($cookie)
    {
        if (is_array($cookie)) {
            $cookie = $cookie[0];
        }

        $result = array();

        $cookieNameValues = explode('&', $cookie);
        $length = count($cookieNameValues);
        for ($i = 0; $i < $length; ++$i) {
            $arr = explode('=', $cookieNameValues[$i]);
            if (count($arr) === 2) {
                $result[$arr[0]] = $arr[1];
            }
        }

        return $result;
    }

    /**
     * Check the validation of the Queue-it cookie.
     *
     * @param array $cookie
     * @param bool $validateTime
     * @return bool
     */
    private function isCookieValid(array $cookie, $validateTime)
    {
        if (!array_key_exists('EventId', $cookie) ||
            !array_key_exists('QueueId', $cookie) ||
            !array_key_exists('RedirectType', $cookie) ||
            !array_key_exists('IssueTime', $cookie) ||
            !array_key_exists('Hash', $cookie)) {
            return false;
        }

        $fixedCookieValidityMinutes = '';
        if (array_key_exists('FixedValidityMins', $cookie)) {
            $fixedCookieValidityMinutes = $cookie['FixedValidityMins'];
        }

        $hashValue = hash_hmac(
            'sha256',
            $cookie['EventId'] . $cookie['QueueId'] . $fixedCookieValidityMinutes . $cookie['RedirectType']
                . $cookie['IssueTime'],
            $this->config['secret_key']
        );
        if ($hashValue !== $cookie['Hash']) {
            return false;
        }

        if (strtolower($this->config['event_id']) !== strtolower($cookie['EventId'])) {
            return false;
        }

        if ($validateTime) {
            $validity = $this->config['cookie_validity'];
            if (!empty($fixedCookieValidityMinutes)) {
                $validity = (int) $fixedCookieValidityMinutes;
            }

            $expirationTime = $cookie['IssueTime'] + ($validity * 60);
            if ($expirationTime < time()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get queue cookie states.
     *
     * @param $validateTime
     * @return array|null
     */
    public function getState($validateTime)
    {
        $cookie = $this->request->cookie($this->getCookieKey());

        if ($cookie === null) {
            return null;
        }

        $cookieNameValueMap = self::getCookieNameValueMap($cookie);
        if (!$this->isCookieValid($cookieNameValueMap, $validateTime)) {
            return null;
        }

        $fixedCookieValidityMinutes = null;
        if (array_key_exists('FixedValidityMins', $cookieNameValueMap)) {
            $fixedCookieValidityMinutes = (int) $cookieNameValueMap['FixedValidityMins'];
        }

        return array(
            'valid' => true,
            'queue_id' => $cookieNameValueMap['QueueId'],
            'fixed_validity' => $fixedCookieValidityMinutes,
            'redirect_type' => $cookieNameValueMap['RedirectType']
        );
    }
}