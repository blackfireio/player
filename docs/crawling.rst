Crawling an HTTP application
============================

Blackfire Player lets you crawl an application via an intuitive PHP API:

.. code-block:: php

    use GuzzleHttp\Client as GuzzleClient;
    use Blackfire\Player\Player;
    use Blackfire\Player\Scenario;

    // create a scenario
    $scenario = new Scenario('Scenario Name');
    $scenario
        ->endpoint('http://example.com')
        ->visit(url('/'))
        ->expect('status_code == 200')
    ;

    // create the HTTP client
    $client = new GuzzleClient([
        // maintain a session between HTTP requests
        'cookies' => true,
    ]);

    // run the scenario
    $player = new Player($client);
    $player->run($scenario);

This simple example shows you how you can make requests on an HTTP application
(``http://examples.com/``) and be sure that it behaves the way you expect it to
via :doc:`Writing Expectations </player/expectations>` (the response status
code is 200).

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

.. tip::

    For big scenarios, you might want to avoid hitting the default PHP timeout
    by adding ``set_time_limit(0);`` to your script.

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

    If your scenarios do not work as expected, :ref:`Enabling Logging
    <player-logging>` might help in getting more information about what's going
    on.

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
        new GuzzleClient(['cookies' => true]),
        new GuzzleClient(['cookies' => true]),
        new GuzzleClient(['cookies' => true]),
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
