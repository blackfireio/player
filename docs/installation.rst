Installation
============

Blackfire Player can be run from PHP or from a YAML file.

Scenarios written in YAML can be run via the Blackfire Player PHAR:

.. code-block:: bash
    :zerocopy:

    curl -OLsS http://get.blackfire.io/blackfire-player.phar

Then, use ``php blackfire-player.phar`` to run the player or make it executable
and move it to a directory in your ``PATH``:

.. code-block:: bash

    chmod 755 blackfire-player.phar
    mv blackfire-player.phar .../bin/blackfire-player

To integrate scenarios into your own PHP code, install Blackfire Player via
Composer:

.. code-block:: bash

    composer require blackfire/player

.. caution::

    Note that Blackfire Player requires **PHP 5.5.9+** and **Guzzle 6+**.

.. note::

    Blackfire Player is licensed under the MIT Open-Source license. Its `source
    code <https://github.com/blackfireio/player>`_ is hosted on Github.
