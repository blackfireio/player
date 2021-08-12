Blackfire Player
================

Blackfire Player is a powerful Web Crawling, Web Testing, and Web Scraper
application. It provides a nice DSL to **crawl HTTP services**, **assert
responses**, and **extract data** from HTML/XML/JSON responses.

Some Blackfire Player use cases:

* Crawl a website/API and check expectations -- aka Acceptance Tests;

* Scrape a website/API and extract values;

* Monitor a website;

* Test code with unit test integration (PHPUnit, Behat, Codeception, ...);

* Test code behavior from the outside thanks to the native Blackfire Profiler
  integration -- aka *Unit Tests from the HTTP layer* (tm).

Read more about how to `download and use Blackfire Player
<https://blackfire.io/docs/builds-cookbooks/player>`_.

Docker
------

You may use ``blackfire-player`` with Docker.
Working directory is expected to be at ``/app`` in the container.

Example running a scenario located in ``my-scenario.bkf`` file:

.. code-block:: bash

    docker run --rm -it -e BLACKFIRE_CLIENT_ID -e BLACKFIRE_CLIENT_TOKEN -v "`pwd`:/app" blackfire/player run my-scenario.bkf

.. note::

    ``BLACKFIRE_CLIENT_ID`` and ``BLACKFIRE_CLIENT_TOKEN`` environment variables
    need to be properly exposed from the host in order to be able to use the `Blackfire
    Profiler integration <https://blackfire.io/docs/integrations/blackfire-player#documentation>`_.

**You may also add a shell alias** (in ``.bashrc``, ``.zshrc``, etc.) for convenience.

.. code-block:: bash

    alias blackfire-player=docker run --rm -it -e BLACKFIRE_CLIENT_ID -e BLACKFIRE_CLIENT_TOKEN -v "`pwd`:/app" blackfire/player

Then, after sourcing your RC file, you can use ``blackfire-player`` as if it was
the binary itself:

.. code-block:: bash

    blackfire-player --version
    blackfire-player list
    blackfire-player run my-scenario.bkf
