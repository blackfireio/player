Installation
============

Blackfire Player Scenarios can be run from PHP or from a YAML file.

YAML files can be run via the Blackfire Player PHAR:

.. code-block:: bash

    curl -OLsS http://get.blackfire.io/blackfire-player.phar

To integrate scenarios into your own PHP code, install Blackfire Player via
Composer:

.. code-block:: bash

    composer require blackfire/player

.. caution::

    Note that Blackfire Player requires **PHP 5.5.9+** and **Guzzle 6+**.

.. note::

    Blackfire Player is licensed under the MIT Open-Source license. Its `source
    code <https://github.com/blackfireio/player>`_ is hosted on Github.
