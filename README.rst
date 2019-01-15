:status: new

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

Blackfire Player executes scenarios written in a special DSL (files should end with ``.bkf``).

Download
--------

Running ``.bkf`` files can be done via the Blackfire Player:

.. code-block:: bash
    :zerocopy:

    curl -OLsS http://get.blackfire.io/blackfire-player.phar

Use ``php blackfire-player.phar`` to run the player or make it executable and
move it to a directory under your ``PATH``:

.. code-block:: bash
    :zerocopy:

    chmod +x blackfire-player.phar
    mv blackfire-player.phar /usr/local/bin/blackfire-player

.. note::

    Blackfire Player is licensed under the MIT Open-Source license. Its `source
    code <https://github.com/blackfireio/player>`_ is hosted on Github.

Usage
-----

Use the ``run`` command to execute a scenario:

.. code-block:: bash

    blackfire-player run scenario.bkf

Use the ``--endpoint`` option to override the endpoint defined in scenarios:

.. code-block:: bash

    blackfire-player run scenario.bkf --endpoint=http://example.com/

Use the ``--concurrency`` option to run scenarios in parallel:

.. code-block:: bash

     blackfire-player run scenario.bkf --concurrency=5

Use the ``--json`` option to output a JSON report:

.. code-block:: bash

    blackfire-player run scenario.bkf --json

Use the ``--variables`` option to override variable values:

.. code-block:: bash

     blackfire-player run scenario.bkf --variables="foo=bar" --variables="bar=foo"

Use ``-v`` to get logs about the progress of the player or use ``tracer`` option
to store all requests and responses on disk.

The command returns 1 if at least one scenario fails, 0 otherwise.

.. _crawling-an-http-application:

Crawling an HTTP application
----------------------------

Blackfire Player lets you crawl an application thanks to descriptive scenarios
written in a domain specific language:

.. code-block:: blackfire

    name "A build made of scenario"

    scenario
        name "Scenario Name"
        endpoint "http://example.com/"

        visit url('/')
            expect status_code() == 200

This example shows how to make a request on an HTTP application
(``http://example.com/``) and be sure that it behaves the way you expect it to
by Writing Expectations (the status code of the response is 200).

Store the scenario in a ``scenario.bkf``, and run it:

.. code-block:: bash

    blackfire-player run scenario.bkf

    # or
    php blackfire-player run scenario.bkf

Add more requests to a scenario by indenting lines as below:

.. code-block:: blackfire

    scenario
        visit url('/')
            expect status_code() == 200

        visit url('/blog/')
            expect status_code() == 200

.. note::

    The line indentation defines the structure like for Python scripts or YAML
    files. Validate ``bkf`` files with the ``validate`` command:
    ``blackfire-player validate scenario.bkf``.

A **scenario** is a sequence of HTTP calls (**steps**) that share the HTTP
session and cookies. Scenario definitions are **declarative**, the order of
settings (like expectations) within a "step" does not matter.

Instead of making discrete requests like above, you can also **interact** with
the HTTP response if the content type is HTML by clicking on links, submitting
forms, or follow redirections (see `Making requests`_ for more information):

.. code-block:: blackfire

    scenario
        visit url('/')
            expect status_code() == 200

        click link('Read more')
            expect status_code() == 200

.. note::

    If your scenario does not work as expected, use ``-v`` to get a more
    verbose output.

.. tip::

    You can add comments in a scenario file by prefixing the line with ``#``:

    .. code-block:: blackfire

        # This is a comment
        scenario
            # Comment are ignored
            visit url('/')
                expect status_code() == 200

.. tip::

    An expression can be written on several lines with the following syntax:

    .. code-block:: blackfire

        scenario
            visit url('/login')
                method 'POST'
                body
                """
                {
                    "user": "john",
                    "password": "doe"
                }
                """

.. _making-requests:

Making Requests
~~~~~~~~~~~~~~~

There are several ways you can jump from one HTTP request to the next.

.. _visiting-a-page-with-visit:

Visiting a Page with ``visit``
++++++++++++++++++++++++++++++

``visit`` goes directly to the referenced HTTP URL (defaults to the ``GET``
HTTP method unless you define one explicitly):

.. code-block:: blackfire

    scenario
        visit url('/')
            method 'POST'

You can also pass a Request body:

.. code-block:: blackfire

    scenario
        visit url('/')
            method 'PUT'
            body '{ "title": "New Title" }'

.. _clicking-on-a-link-with-click:

Clicking on a Link with ``click``
+++++++++++++++++++++++++++++++++

``click`` clicks on a link in an HTML page (takes an expression as an argument):

.. code-block:: blackfire

    scenario
        click link("Add a blog post")

.. _submitting-forms-with-submit:

Submitting Forms with ``submit``
++++++++++++++++++++++++++++++++

``submit`` submits a form in an HTML page (takes an expression as an argument);
parameters to submit with the form are defined via ``param`` entries:

.. code-block:: blackfire

    scenario
        submit button("Submit")
            param title 'Happy Scraping'
            param content 'Scraping with Blackfire Player is so easy!'

            # File Upload:
            # the path is relative to the current .bkf file
            # the name parameter is optional
            param image file('relative/path/to/image.png', 'blackfire.png')

Values can also be randomly generated via the ``fake()`` function:

.. code-block:: blackfire

    scenario
        submit button("Submit")
            param title fake('sentence', 5)
            param content join(fake('paragraphs', 3), "\n\n")

.. note::

    ``fake()`` use the `Faker library <https://github.com/fzaninotto/Faker>`_
    under the hood.

.. _following-redirections:

Following Redirections
++++++++++++++++++++++

HTTP redirections are never followed automatically to let you write
expectations and assertions on redirect responses:

.. code-block:: blackfire

    scenario
        visit "redirect.php"
            expect status_code() == 302
            expect header('Location') == '/redirected.php'

Use ``follow`` to follow one redirection:

.. code-block:: blackfire

    scenario
        visit "redirect.php"
            expect status_code() == 302
            expect header('Location') == '/redirected.php'

        follow
            expect status_code() == 200

``follow_redirects`` switches the player to automatically follow all
redirections:

.. code-block:: blackfire

    scenario
        follow_redirects true

or:

.. code-block:: blackfire

    scenario
        visit "redirect.php"
            follow_redirects

Please note that when using ``follow_redirects``, expectations (``expect``)
and assertions (``assert``) are checked on the redirecting response
(so, before the redirection).
Use a ``follow`` step if you need to check them after the redirection.

.. _embedding-scenarios-with-include:

Embedding Scenarios with ``include``
++++++++++++++++++++++++++++++++++++

``include`` allows to embed some repetitive steps into several scenarios to
avoid copy/pasting the same code over and over again:

In a ``login.bkf`` file, write a ``group`` that contains the logic to log in:

.. code-block:: blackfire

    group login
        visit url('/login')
            expect status_code() == 200

        submit button('Login')
            param user 'admin'
            param password 'admin'

Then, in another file, ``load`` the ``group`` and ``include`` it when you need
it:

.. code-block:: blackfire

    load "groups.bkf"

    scenario
        name "Scenario Name"

        include login

        visit url('/admin')
            expect status_code() == 200

.. _configuring-the-request:

Configuring the Request
~~~~~~~~~~~~~~~~~~~~~~~

Each step can be configured via the following options.

.. _setting-a-header-with-header:

Setting a Header with ``header``
++++++++++++++++++++++++++++++++

``header`` sets a header:

.. code-block:: blackfire

    scenario
        visit url('/')
            header "Accept-Language: en-US"

.. tip::

    Simulate a specific browser is as simple as overriding the default
    ``User-Agent`` and using ``fake()``:

    .. code-block:: blackfire

        scenario
            visit url('/')
                header 'User-Agent: ' ~ fake('firefox')

.. _setting-a-user-and-password-with-auth:

Setting a User and Password with ``auth``
+++++++++++++++++++++++++++++++++++++++++

``auth`` sets the ``Authorization`` header:

.. code-block:: blackfire

    scenario
        visit url('/')
            auth "username:password"

.. _waiting-after-sending-the-request-with-wait:

Waiting after sending the request with ``wait``
+++++++++++++++++++++++++++++++++++++++++++++++

``wait`` adds a delay in milliseconds after sending the request:

.. code-block:: blackfire

    scenario
        visit url('/')
            wait 10000

The ``wait`` value can be any valid expression; get a random delay by using
``fake()``:

.. code-block:: blackfire

    scenario
        visit url('/')
            wait fake('numberBetween', 1000, 3000)

.. _sending-a-json-body-with-json:

Sending a JSON Body with ``json``
+++++++++++++++++++++++++++++++++

``json`` configures the Request to upload JSON encoded data as the body:

.. code-block:: blackfire

    scenario
        visit url('/')
            method 'POST'
            param foo "bar"
            json true

.. _setting-options-for-all-steps:

Setting Options for all Steps
+++++++++++++++++++++++++++++

You can also set some of these options for all steps of a scenario:

.. code-block:: blackfire

    scenario
        auth "username:password"
        header "Accept-Language: en-US"

... which can be disabled on any given step by setting the value to ``false``:

.. code-block:: blackfire

    scenario
        visit url('/')
            header "Accept-Language: false"
            auth false

.. _writing-expectations:

Writing Expectations
--------------------

Expectations are **expressions** evaluated against the current HTTP response
and if one of them returns a *falsy* value, Blackfire Player stops the run and
generates an error.

Expressions have access to the following functions:

* ``current_url()``: Returns the current URL

* ``status_code()``: The HTTP status code for the current HTTP response;

* ``header()``: Returns the value of an HTTP header;

* ``body()``: The HTTP body for the current HTTP response;

* ``trim()``: Strip whitespace from the beginning and end of a string;

* ``unique()``: Removes duplicate values from an array;

* ``join()``: Join array elements with a string;

* ``merge()``: Merge one or more arrays;

* ``regex()``: Perform a regular expression match;

* ``css()``: Returns nodes matching the CSS selector (for HTML responses);

* ``xpath()``: Returns nodes matching the XPath selector (for HTML and XML
  responses);

* ``json()``: Returns JSON elements (from the request) matching the CSS expression.

* ``transform()``: Returns JSON elements matching the CSS expression.

The ``css()`` and ``xpath()`` functions return
``Symfony\Component\DomCrawler\Crawler`` instances. Learn more about `methods
you can call on Crawler instances
<http://symfony.com/doc/current/components/dom_crawler.html>`_; the ``json()``
function returns a PHP array.

The ``json()`` function accepts `JMESPath
<http://jmespath.org/specification.html>`_.

The result of calling functions can be checked via `operators
<http://symfony.com/doc/current/components/expression_language/syntax.html#supported-operators>`_ described.

.. note::

    Learn more about `Expressions syntax
    <http://symfony.com/doc/current/components/expression_language/syntax.html>`_
    in the Symfony documentation.

Here are some expression examples:

.. code-block:: blackfire

    # return all HTML nodes matching ".post h2 a"
    css(".post h2 a")

    # return the text of the first node matching ".post h2 a"
    css(".post h2 a").first().text()

    # return the href attribute of the first node matching ".post h2 a"
    css(".post h2 a").first().attr("href")

    # check that "h1" contains "Welcome"
    css("h1:contains('Welcome')").count() > 0

    # same as above
    css("h1").first().text() matches "/Welcome/"

    # return the Age request HTTP header
    header("Age")

    # check that the HTML body contains "Welcome"
    body() matches "/Welcome/"

    # get a value
    json("_links.store.href")

    # get keys
    json("arguments."sql.pdo.queries".keys(@)")

.. _using-variables:

Using Variables
---------------

Variables can be defined to make your scenarios dynamic. Use ``set`` to define
the default value:

.. code-block:: blackfire

    scenario
        name "HTTP Cache"
        set env "dev"
        set urls [ ... ]

        when "prod" == env
            with url in urls
                # check HTTP cache, but only on production

And override it with the ``--variable`` option on the CLI:

.. code-block:: bash

    blackfire-player run scenario.bkf --variable env=prod

Organizing Scenario Files
-------------------------

To run scenarios defined in several files, you can use ``load`` instead of
listing all the files as arguments to the player:

.. code-block:: blackfire

    # load and execute all scenarios from files in this directory
    load "*.bkf"

    # load and execute all scenarios from files in all sub-ddirectories
    load "**/*.bkf"

.. _writing-blackfire-assertions:

Writing Blackfire Assertions
----------------------------

Blackfire Player natively supports Blackfire:

.. code-block:: bash

    blackfire-player run scenario.bkf

The Blackfire Player creates a build to group all scenarios.
Each scenario in the build contains profiles and assertion reports for requests made in the executed scenario;

.. code-block:: blackfire

    scenario
        name "Scenario Name"
        # Use the environment name (or UUID) you're targeting or false to disable
        blackfire "Environment name"

It's possible to use ``true`` instead of an environment name. In that case, the
environment name should be set via the ``--blackfire-env`` CLI option:

.. code-block:: blackfire

    scenario
        name "Scenario Name"
        # Use the environment name (or UUID) you're targeting or false to disable
        blackfire true

.. code-block:: bash

    blackfire-player run scenario.bkf --blackfire-env="Environment name" # Use the environment name or environment UUID

.. note::

    You can set the ``external_id`` and ``external_parent_id`` settings of the
    build by passing environment variables:

    .. code-block:: bash

        BLACKFIRE_EXTERNAL_ID=ref BLACKFIRE_EXTERNAL_PARENT_ID=parent \
        blackfire-player run scenario.bkf --blackfire-env=ENV_NAME_OR_UUID

When Blackfire support is enabled, the assertions defined in ``.blackfire.yml``
are automatically run along side expectations.

Additional features are also automatically activated:

* **assert**: An assertion to check

* **samples**: The number of samples

* **warmup**: Whether to warmup the URL first. Value can be:

    * **true**: Warmup only safe HTTP requests or when the number of samples is more than one.
      Warmup will be executed 3 times. (default value)

    * **A number**: Same behavior than **true**, but allow to change the number of warmup requests.

    * **false**: Disable warmup

.. code-block:: blackfire

    scenario
        visit url('/blog/')
            name "Blog homepage"
            assert main.peak_memory < 10M
            samples 2
            warmup true

By default, all requests are profiled via Blackfire, you can disable it for
some requests by calling ``blackfire(false)``.

Variables are a great way to make your Blackfire assertions conditional:

.. code-block:: blackfire

    scenario
        set env "prod"

        # no Twig template compilation in production
        # not enforced in other environments
        visit url('/blog/')
            assert "prod" == env and metrics.twig.compile.count == 0
            warmup true

.. _scraping-values:

Scraping Values
---------------

When crawling an HTTP application you can extract values from HTTP responses:

.. code-block:: blackfire

    scenario
        visit url('/')
            expect status_code() == 200
            set latest_post_title css(".post h2").first()
            set latest_post_href css(".post h2 a").first().attr("href")
            set latest_posts css(".post h2 a").extract('_text', 'href')
            set age header("Age")
            set content_type header("Content-Type")
            set token regex('/name="_token" value="([^"]+)"/')

``set`` takes two arguments:

* The name of the variable you want to store the value in;

* An expression to evaluate.

Using ``json()``, ``css()``, and ``xpath()`` on JSON, HTML, and XML responses
is recommended, but for pure text responses or complex values, you can use the
generic ``regex()`` function.

.. note::

    ``regex()`` takes a regex as an argument and always returns the first
    match. Note that backslashes must be escaped by doubling them:
    ``"/\\.git/"``.

The values are also available at the end of a crawling session:

.. code-block:: bash

    # use --json to display a report including variable values
    blackfire-player run scenario.bkf --json

Variable values can also be injected before running another scenario:

.. code-block:: blackfire

    scenario
        name "Scenario name"
        auth api_username ~ ':' ~ api_password
        set profile_uuid 'zzzz'

        visit url('/profiles' ~ profile_uuid)
            expect status_code() == 200
            set sql_queries json('arguments."sql.pdo.queries".keys(@)')
            set store_url json("_links.store.href")

        visit url(store_url)
            method 'POST'
            body '{ "foo": "batman" }'
            expect status_code() == 200
