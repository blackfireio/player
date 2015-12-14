:access: ROLE_ADMIN

Configuring Player
==================

.. _player-logging:

Enabling Logging
----------------

To debug your scenarios, use a PSR Logger like Monolog:

.. code-block:: php

    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;

    $logger = new Logger('player');
    $logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

    $player->setLogger($logger);
