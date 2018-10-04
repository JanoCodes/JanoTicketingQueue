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

namespace Jano\Modules\Queue\Models;

use Illuminate\Database\Eloquent\Model;

class QueueParams extends Model
{
    private const TIMESTAMP_KEY = 'ts';
    private const EXTENDABLE_COOKIE_KEY = 'ce';
    private const COOKIE_VALIDITY_MINUTES_KEY = 'cv';
    private const HASH_KEY = 'h';
    private const EVENT_ID_KEY = 'e';
    private const QUEUE_ID_KEY = 'q';
    private const REDIRECT_TYPE_KEY = 'rt';
    private const KEY_VALUE_SEPARATOR = '_';
    private const KEY_VALUE_SEPARATOR_GROUP = '~';

    /**
     * @var int
     */
    public $timestamp = 0;

    /**
     * @var string
     */
    public $eventId = '';

    /**
     * @var string
     */
    public $hash = '';

    /**
     * @var bool
     */
    public $extendableCookie = false;

    /**
     * @var int|null
     */
    public $cookieValidityMinutes;

    /**
     * @var string
     */
    public $token = '';

    /**
     * @var string
     */
    public $tokenWithoutHash = '';

    /**
     * @var string
     */
    public $queueId = '';

    /**
     * @var string
     */
    public $redirectType = '';

    /**
     * Extract parameters from token.
     *
     * @param string $token
     * @return \Jano\Modules\Queue\Models\QueueParams
     */
    public static function extractQueueParams($token)
    {
        $result = new self();
        $result->token = $token;

        $paramsNameValueList = explode(self::KEY_VALUE_SEPARATOR_GROUP, $result->token);

        foreach ($paramsNameValueList as $pNameValue) {
            $paramNameValueArr = explode(self::KEY_VALUE_SEPARATOR, $pNameValue);

            switch ($paramNameValueArr[0]) {
                case self::TIMESTAMP_KEY:
                    $result->timestamp = is_numeric($paramNameValueArr[1]) ? (int) $paramNameValueArr[1] : 0;
                    break;
                case self::COOKIE_VALIDITY_MINUTES_KEY:
                    if (is_numeric($paramNameValueArr[1])) {
                        $result->cookieValidityMinutes = (int) $paramNameValueArr[1];
                    }
                    break;
                case self::EVENT_ID_KEY:
                    $result->eventId = $paramNameValueArr[1];
                    break;
                case self::EXTENDABLE_COOKIE_KEY:
                    $result->extendableCookie = $paramNameValueArr[1] === 'True' || $paramNameValueArr[1] === 'true';
                    break;
                case self::HASH_KEY:
                    $result->hash = $paramNameValueArr[1];
                    break;
                case self::QUEUE_ID_KEY:
                    $result->queueId = $paramNameValueArr[1];
                    break;
                case self::REDIRECT_TYPE_KEY:
                    $result->redirectType = $paramNameValueArr[1];
                    break;
            }
        }

        $result->tokenWithoutHash = str_replace(
            self::KEY_VALUE_SEPARATOR_GROUP
            . self::HASH_KEY
            . self::KEY_VALUE_SEPARATOR
            . $result->hash,
            '',
            $result->token
        );

        return $result;
    }
}