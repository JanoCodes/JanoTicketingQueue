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

namespace Jano\Modules\Queue\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Jano\Modules\Queue\Helpers\QueueHelper;
use Jano\Modules\Queue\Models\QueueParams;
use Setting;

class ValidateQueueRequest
{
    /**
     * @var \Jano\Modules\Queue\Helpers\QueueHelper
     */
    private $helper;

    /**
     * ValidateQueueRequest middleware constructor.
     *
     * @param \Jano\Modules\Queue\Helpers\QueueHelper $helper
     */
    public function __construct(QueueHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Setting::get('system.queue_active')) {
            return $next($request);
        }

        $state = $this->helper->getState(true);

        if ($state['valid']) {
            if ($state['fixed_validity'] === null && config('queue::extend_validity')) {
                $this->helper->createCookie($state['queue_id'], null, $state['redirect_type']);
            }

            return $next($request);
        }

        if ($request->get('queueittoken') !== null) {
            $queueParams = QueueParams::extractQueueParams($request->get('queueittoken'));

            $result = $this->helper->getTokenValidationResult($queueParams);
            if ($result) {
                return $next($request);
            }
        }

        return redirect($this->helper->getRedirectUrl($request->fullUrl()));
    }
}