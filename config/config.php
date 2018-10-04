<?php

return [
    'customer_id' => 'customer',
    'secret_key' => 'secret-key',
    'event_id' => 'test',
    'domain' => 'customer.queue-it.net',

    /**
     * The number of minutes before the user need to enter the queue again.
     */
    'cookie_validity' => 20,

    /**
     * Whether an action on the site would renew the time the user can remain on the site without having to enter the
     * queue again.
     */
    'extend_validity' => true,

    'layout' => '',
    'version' => '1.0.0.0',
];
