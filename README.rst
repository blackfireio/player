Blackfire Player
================

Blackfire Player is a powerful performance testing application.
It provides a nice DSL to **crawl HTTP services**, **assert
responses**, and **extract data** from HTML/XML/JSON responses.

Read more about how to `download and use Blackfire Player
<https://docs.blackfire.io/builds-cookbooks/player>`_.

Usage
-----

``blackfire-player`` is distributed through a Docker image.

To run a scenario located in the ``my-scenario.bkf`` file, run the following
command:

.. code-block:: bash

    docker run --rm -it -e BLACKFIRE_CLIENT_ID -e BLACKFIRE_CLIENT_TOKEN -v "`pwd`:/app" blackfire/player run my-scenario.bkf

The ``pwd`` part is the local working directory (we are using the current
directory here) and it is mapped to the ``/app`` path in the Docker container.

``BLACKFIRE_CLIENT_ID`` and ``BLACKFIRE_CLIENT_TOKEN`` environment variables
need to be properly exposed from the host in order to be able to use the
:doc:`Blackfire Profiler integration </integrations/blackfire-player>`.

.. note::

    To make it simpler to run this command, you might create a shell alias
    (that you can store in a ``.bashrc`` or ``.zshrc`` file depending on your
    shell):

    .. code-block:: bash

        alias blackfire-player="docker run --rm -it -e BLACKFIRE_CLIENT_ID -e BLACKFIRE_CLIENT_TOKEN -v \"`pwd`:/app\" blackfire/player"

    Don't forget to restart your terminal for it to take effect. You can then
    use ``blackfire-player`` as if it was the binary itself:

    .. code-block:: bash

        blackfire-player --version
        blackfire-player list
        blackfire-player run my-scenario.bkf
