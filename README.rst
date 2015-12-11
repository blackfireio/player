Blackfire Player
================

Blackfire Player is a powerful Web Crawling and Web Scraper library for PHP. It
provides a nice API to **crawl HTTP services** and **extract data** from the
HTML/XML/JSON responses.

Some Blackfire Player uses cases:

* Crawl your website/API and check expectations -- aka Acceptance Tests;

* Scrape your website/API and extract values;

* Use Blackfire Player in your tests (PHPUnit, Behat, Codeception, ...);

* Test your code behavior from the outside thanks to the native Blackfire
  integration -- aka *Unit Tests from the HTTP layer*(tm).

Installation
------------

Install Blackfire Player via Composer:

.. code-block:: bash

    composer require blackfire/player

Note that Blackfire Player requires PHP 5.5.9+ and Guzzle 6+.

Crawling an HTTP application
----------------------------

.. code-block:: php

    use GuzzleHttp\Client as GuzzleClient;
    use Blackfire\Player\Player;
    use Blackfire\Player\Scenario;

    $client = new GuzzleClient([
        'base_uri' => 'http://example.com',
        // to maintain a session between HTTP requests
        'cookies' => true,
    ]);

    $player = new Player($client);

    // create a scenario
    $scenario = new Scenario('Scenario Name');
    $scenario
        ->visit(url('/'))
        ->expect('status_code == 200')
    ;

    // run the scenario
    $player->run($scenario);

This simple example shows you how you can make requests on an HTTP application
(``http://examples.com/``) and be sure that it behaves the way you expect it to
via `Writing Expectations`_ (the response status code is 200).

You can chain requests:

.. code-block:: php

    $scenario = new Scenario();
    $scenario
        ->visit(url('/'))
        ->expect('status_code == 200')

        ->visit(url('/blog/'))
        ->expect('status_code == 200')
    ;

    $player->run($scenario);

A **scenario** is a sequence of HTTP calls (**steps**) that share the HTTP
cookies/session. Scenario definitions are **declarative**, the order of method
calls within a "step" do not matter.

Instead of making discrete requests like above, you can also **interact** with
the HTTP response if the content type is HTML by clicking on links, submitting
forms, or follow redirections (see `Making requests`_ for more information):

.. code-block:: php

    $scenario = new Scenario();
    $scenario
        ->visit(url('/'))
        ->expect('status_code == 200')

        ->click('link("Read more")')
        ->expect('status_code == 200')
    ;

    $player->run($scenario);

.. tip::

    Running more than one scenarios via ``run()`` is safe as the client
    **cookies are cleared at the end of each scenario**.

.. note::

    If your scenarios do not work as expected, `Enabling Logging`_ might help
    in getting more information about what's going on.

Making Requests
---------------

There are several ways you can jump from on HTTP request to the next:

* ``visit()``: Go directly to the referenced HTTP URL (defaults to the ``GET``
  HTTP method unless you pass one explicitly as a second argument):

  .. code-block:: php

      $scenario->visit(url('/blog'), 'POST');

  You can also pass the Request body as a third argument:

  .. code-block:: php

      $scenario->visit(url('/blog'), 'PUT', '{ "title": "New Title" }');

* ``click()``: Click on a link in an HTML page (takes an expression as an
  argument):

  .. code-block:: php

      // reference a link via the ``link()`` function
      $scenario->click('link("Add a blog post")');

* ``submit()``: Submit a form in an HTML page (takes an expression as an
  argument and an array of values to submit with the form):

  .. code-block:: php

      // reference a button via the ``button()`` function
      $scenario->submit('button("Submit")', [
          'title' => "'Happy Scraping'",
          'content' => "'Scraping with Blackfire Player is so easy!'",
      ]);

  Note that submitted values are expressions, so you need to quote plain
  strings.

* ``follow()``: Follows a redirection (redirections are never followed
  automatically to let you write expectations and assertions on all requests):

  .. code-block:: php

      $scenario->follow();

* ``add()``: Embeds a scenario into another one:

  .. code-block:: php

      use Blackfire\Player\Scenario;

      $loginScenario = new Scenario('Login');
      $loginScenario
          ->visit(url('/login'))
          ->expect('status_code == 200')
          ->submit('button("Login")', ['user' => "'admin'", 'password' => "'admin'"])
          ->expect('status_code == 200')
      ;

      $scenario = new Scenario('Symfony Blog');
      $scenario
          ->add($loginScenario)
          ->visit(url('/admin'))
          ->expect('status_code == 200')
      ;

  Scenarios can be embedded at any step in a scenario.

Configure the Request
---------------------

Each step can be configured via the following options:

* ``header()``: Sets a header:

  .. code-block:: php

      $scenario
          ->visit(url('/'))
          ->header('Accept-Language', 'en-US')
      ;

* ``auth()``: Sets the ``Authorization`` header:

  .. code-block:: php

      $scenario
          ->visit(url('/'))
          ->auth('username', 'password')
      ;

* ``delay()``: Adds a delay in milliseconds before sending the request:

  .. code-block:: php

      $scenario
          ->visit(url('/'))
          ->delay(10000)
      ;

* ``json()``: Configures the Request to upload JSON encoded data as the body:

  .. code-block:: php

      $scenario
          ->visit(url('/'), 'POST', ['foo': 'bar'])
          ->json()
      ;

You can also set some of these options for all steps of a scenario:

.. code-block:: php

    $scenario
        ->auth('username', 'password')
        ->header('Accept-Language', 'en-US')
    ;

... which can be disabled on any given step by setting the value to ``false``:

.. code-block:: php

    $scenario
        ->header('Accept-Language', false)
        ->auth(false)
    ;

Writing Expectations
--------------------

Expectations are **expressions** evaluated against the current HTTP response
and if one of them returns a falsy value, Blackfire Player throws a
``Blackfire\Player\Exception\ExpectationFailureException`` exception.

Expressions have access to the following variables:

* ``status_code``: The HTTP status code for the current HTTP response;

* ``body``: The HTTP body for the current HTTP response.

Expressions can also use the following functions:

* ``header()``: Returns the value of an HTTP header;

* ``css()``: Returns nodes matching the CSS selector (for HTML responses);

* ``xpath()``: Returns nodes matching the XPath selector (for HTML and XML
  responses);

* ``json()``: Returns JSON elements matching the CSS expression (for JSON
  responses; see `JMESPath <http://jmespath.org/specification.html>`_ for the
  syntax);

Learn more about the `Expression syntax
<http://symfony.com/doc/current/components/expression_language/syntax.html>`_.

The ``css()`` and ``xpath()`` functions return
``Symfony\Component\DomCrawler\Crawler`` instances. Learn more about `methods
you can call on Crawler instances
<http://symfony.com/doc/current/components/dom_crawler.html>`_; the ``json()``
function returns a PHP array.

Here are some common expressions:

.. code-block:: php

    // return all HTML nodes matching ".post h2 a"
    'css(".post h2 a")'

    // return the first node matching ".post h2 a"
    'css(".post h2 a").first'

    // check that "h1" contains "Welcome"
    'css("h1:contains(\'Welcome\')")'

    // same as above
    'css("h1").first().text() matches "/Welcome/"'

    // return the Age request HTTP header
    'header("Age")'

    // check that the HTML body contains "Welcome"
    'body matches "/Welcome/"'

    // extract a value
    'json("_links.store.href")'

    // extract keys
    'json("arguments.\"sql.pdo.queries\".keys(@)")'

Extracting Values
-----------------

When crawling an HTTP application you can extract values from HTTP responses:

.. code-block:: php

    $scenario
        ->visit(url('/blog/'))
        ->expect('status_code == 200')
        ->extract('latest_post_title', 'css(".post h2").first()')
        ->extract('latest_post_href', 'css(".post h2 a").first()', 'href')
        ->extract('latest_posts', 'css(".post h2 a")', ['_text', 'href'])
        ->extract('header("Age")')
        ->extract('header("Content-Type")')
    ;

The ``extract()`` method takes three arguments:

* The name of the variable you want to store the extracted value in;

* An expression to evaluate (the value of the evaluated expression);

* *Optionally*, an attribute to extract or an array of attributes to extract
  (use `_text` to extract the node text value, which is the default).

The extracted values are also available at the end of a crawling session:

.. code-block:: php

    $result = $player->run($scenario);
    $value = $result['latest_post_title'];

    // get all values
    $values = $result->getValues();

    // iterate over all values
    foreach ($result as $key => $value) {
        // ...
    }

Extracted values can be used in expressions for subsequent requests via the
as regular expression variables:

.. code-block:: php

    $scenario
        ->visit(url('/blog/'))
        ->expect('status_code == 200')
        ->expect('css(".posts")')
        ->extract('latest_post_title', 'css(".post h2 a").first()')

        ->click('link(latest_post_title)')
        ->expect('css("h1:contains(latest_post_title)")')

        ->submit('button("Comment")', [
            'content' => "'Scraping with Blackfire Player is so easy (' ~ latest_post_title ~ ')!'",
        ])
    ;

Variable values can also be injected before running a scenario (via the
``Scenario`` constructor or the ``value()`` method), making it possible to
**parametrize scenarios**:

.. code-block:: php

    $scenario = new Scenario('Scenario Title', ['current_year' => 2016]);
    $scenario
        ->value('current_year' => 2016)
        ->visit(url('/blog/'))
        ->expect('status_code == 200')
        ->expect('css(".copyright_year") matches /current_year/')
    ;

    $player->run($scenario);

Variables can be used to **conditionally execute scenarios** based on some
values:

.. code-block:: php

    $scenario = new Scenario();
    $scenario
        ->visit(url('/blog/'))
        ->expect('status_code == 200')
        ->extract('post_url', 'css(".posts")', 'href')
    ;

    $result = $player->run($scenario);

    if ($result['post_url']) {
        $player->run($anotherScenario);
    }

Here is another example for a JSON API:

.. code-block:: php

    $scenario = new Scenario('Scenario title', [
        'api_username' => 'xxxx',
        'api_password' => 'yyyy',
        'profile_uuid' => 'zzzz',
    ]);

    $scenario
        ->auth('api_username', 'api_password')

        ->visit(url('profiles/' ~ profile_uuid))
        ->expect('status_code == 200')
        ->extract('sql_queries', 'json("arguments.\"sql.pdo.queries\".keys(@)")')
        ->extract('store_url', 'json("_links.store.href")')

        ->visit('url(store_url)', 'POST', '{ "foo": "batman" }')
        ->expect('status_code == 202')
    ;

    $player->run($scenario);

Enabling Logging
----------------

To debug your scenarios, use a PSR Logger like Monolog:

.. code-block:: php

    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;

    $logger = new Logger('player');
    $logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

    $player->setLogger($logger);

Running Multiple Scenarios
--------------------------

Instead of running your scenarios one after the other via ``run()`` calls,
store them in a ``ScenarioSet`` instance and run them via ``runMulti()``:

.. code-block:: php

    use Blackfire\Player\ScenarioSet;
    use Blackfire\Player\Scenario;

    $scenarios = new ScenarioSet();

    $scenarios->add($scenario = new Scenario('Blog'));
    $scenario
        ->visit(url('/blog/'))
        ->title('Blog homepage')
        ->expect('status_code == 200')

        // ...
    ;

    $scenarios->add($scenario = new Scenario('Homepage'));
    $scenario
        ->visit(url('/'))

        // ...
    ;

    $results = $player->runMulti($scenarios);

``runMulti()`` returns an array of ``Result`` instances (in the same order as
the scenarios stored in ``ScenarioSet``). Like with ``run()``, each scenario is
run independently from the other ones (cookies are cleared).

One benefit of ``runMulti`` is its ability to **run scenarios in parallel**
when you pass multiple instance of clients to Blackfire Player:

.. code-block:: php

    $baseUri = 'http://example.com';
    $clients = [
        new GuzzleClient(['base_uri' => $baseUri, 'cookies' => true]),
        new GuzzleClient(['base_uri' => $baseUri, 'cookies' => true]),
        new GuzzleClient(['base_uri' => $baseUri, 'cookies' => true]),
    ];

    $player = new Player($clients);

``runMulti()`` automatically computes the best number of concurrent scenarios
to run in parallel depending on the number of clients and scenarios. You can
also explicitly set the level of concurrency:

.. code-block:: php

    // 2 concurrent runs
    $player = new Player($clients);
    $player->runMulti($scenarios, 2);

When defining multiple scenarios, you can factor out re-usable scenarios (like
login, account creation, or deletion steps, ...):

.. code-block:: php

    // create a login scenario
    $loginScenario = new Scenario('Login');
    $loginScenario
        ->visit(url('/login'))
        ->expect('status_code == 200')
        ->submit('button("Login")', ['user' => "'admin'", 'password' => "'admin'"])
        ->expect('status_code == 200')
    ;

    $scenarios = new ScenarioSet();

    // add a first scenario that needs to be logged-in
    $scenarios->add($scenario = new Scenario('Blog'));
    $scenario
        ->add($loginScenario)
        ->visit(url('/stats/'))

        // ...
    ;

    // add a second scenario that needs to be logged-in
    $scenarios->add($scenario = new Scenario('Homepage'));
    $scenario
        ->add($loginScenario)
        ->visit(url('/admin/'))

        // ...
    ;

    $results = $player->runMulti($scenarios);

Writing Blackfire Assertions
----------------------------

.. caution::

    This feature does not work yet!

Blackfire Player natively supports Blackfire:

.. code-block:: php

    use Blackfire\Client as BlackfireClient;
    use Blackfire\ClientConfiguration;

    $blackfireConfig = new ClientConfiguration(null, null, 'Env name');
    $blackfire = new BlackfireClient($blackfireConfig);

    $player = new Player($client);

    // enable the Blackfire extension
    $player->addExtension(new \Blackfire\Player\Extension\BlackfireExtension($blackfire, $logger));

When running a scenario, Blackfire creates a build that will contain all
profiles and assertion reports for requests made in the executed scenario; the
scenario name is then used as the build name:

.. code-block:: php

    $scenario = new Scenario($scenario, 'Scenario Name');

When Blackfire support is enabled, Blackfire Player supports some additional
features:

.. code-block:: php

    $scenario
        ->visit(url('/blog/'))

        // set a title
        ->title('Blog homepage')

        // add a Blackfire assertion
        ->assert('main.peak_memory < 10M')

        // take 2 samples
        ->samples(2)

    $result = $player->run($scenario);

By default, all requests are profiled via Blackfire, you can disable it for
some requests by calling ``blackfire(false)``.

You can easily access the Blackfire Report via the Result returned by
``run()``:

.. code-block:: php

    $report = $result->getExtra('blackfire_report');

Variables are a great way to make your Blackfire assertions conditional:

.. code-block:: php

    $scenario
        ->value('env', 'prod')
        ->visit(url('/blog/'))

        // no Twig template compilation in production
        // not enforced on other environments
        ->assert('"prod" == env and metrics.twig.compile.count == 0')
    ;

    $player->run($scenario);

Scenarios as YAML Files
-----------------------

Scenarios can be described using YAML:

.. code-block:: php

    use Blackfire\Player\Loader\YamlLoader;

    $loader = new YamlLoader();
    $scenario = $loader->load(file_get_contents('scenario.yml'));

Here is an example of a YAML scenario that uses all Blackfire Player features:

.. code-block:: yaml

    scenario:
        options:
            title: Blackfire Player Scenario
            auth: [foo, bar]

        steps:
            - title: "Blog Homepage"
              visit: url('/blog/')
              assert:
                  - main.peak_memory < 10M
              samples: 2
              title: Blog homepage
              headers:
                  'Accept-Language': 'en-US'
              expect:
                  - status_code == 200
                  - header('content_type') matches '/html/'
                  - css('body')

            - title: "Releases"
              click: link('Releases')
              expect:
                  - status_code == 200
                  - css('body.releases')
              extract:
                  latest_release_title: 'css(".post h2 a").first()'
                  latest_release_href: ['css(".post h2 a").first()', 'href']
                  latest_releases: ['css(".post h2 a")', ['_text', 'href']]

            - title: "Latest Release Blog Post"
              click: link(latest_release_title)
              expect:
                  - css("h1:contains(latest_release_title)")
              blackfire: false

You can also define multiple scenarios in a single YAML file:

.. code-block:: yaml

    scenarios:
        -
            options:
                # tag the scenario to make it re-usable
                key: 'home'

            steps:
                - title: "Homepage"
                  visit: url('/')

        -
            options:
                title: 'Symfony Blog'
                auth: [foo, bar]

            steps:
                # embed the 'home' scenario
                - add: home

                - title: "Blog Homepage"
                  visit: url('/blog/')

                - title: "JSON API"
                  visit: url('/api')
                  method: POST
                  params:
                      foo: "'bar'"
                  json: true

Note that scenarios defined with a key are abstract and are not be run by
``runMulti()``.

.. tip::

    When Blackfire support is enabled, scenarios defined in ``.blackfire.yml``
    files are also supported.

Scenarios as a PHP Array
------------------------

Scenarios can be described via a PHP array:

.. code-block:: php

    $scenario = [
        'steps' => [
            [
                'title' => 'Blog Homepage',
                'url' => '/blog/',
                'expect' => [
                    'status_code == 200',
                ],
            ],
        ],
    ];

    use Blackfire\Player\Loader\ArrayLoader;

    $loader = new ArrayLoader();
    $scenario = $loader->load($scenario);

The syntax of the array is the same as the YAML structure.
