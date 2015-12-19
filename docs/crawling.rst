Crawling an HTTP application
============================

Blackfire Player lets you crawl an application thanks to descriptive scenarios
written with PHP or YAML:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            options:
                title: Scenario Name
                endpoint: http://example.com/

            steps:
                - visit: url('/')
                  expect:
                      - status_code() == 200

    .. code-block:: php

        use GuzzleHttp\Client as GuzzleClient;
        use Blackfire\Player\Player;
        use Blackfire\Player\Scenario;

        // create a scenario
        $scenario = new Scenario('Scenario Name');
        $scenario
            ->endpoint('http://example.com')
            ->visit("url('/')")
            ->expect('status_code() == 200')
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
(``http://example.com/``) and be sure that it behaves the way you expect it to
via :doc:`Writing Expectations </player/expectations>` (the response status
code is 200).

If you are using the YAML representation for scenarios, run them via the
`Blackfire Player console tool </player/cli>`_:

.. code-block:: bash

    blackfire-player run scenario.yml

    # or
    php blackfire-player.phar run scenario.yml

You can chain requests:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  expect:
                      - status_code() == 200

                - visit: url('/blog/')
                  expect:
                      - status_code() == 200

    .. code-block:: php

        $scenario = new Scenario();
        $scenario
            ->visit("url('/')")
            ->expect('status_code() == 200')

            ->visit("url('/blog/')")
            ->expect('status_code() == 200')
        ;

        $player->run($scenario);

A **scenario** is a sequence of HTTP calls (**steps**) that share the HTTP
session and cookies. Scenario definitions are **declarative**, the order of
method calls within a "step" does not matter.

.. tip::

    For big scenarios, you might want to avoid hitting the default PHP timeout
    by adding ``set_time_limit(0);`` to your script.

Instead of making discrete requests like above, you can also **interact** with
the HTTP response if the content type is HTML by clicking on links, submitting
forms, or follow redirections (see `Making requests`_ for more information):

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  expect:
                      - status_code() == 200

                - click: link('Read more')
                  expect:
                      - status_code() == 200

    .. code-block:: php

        $scenario = new Scenario();
        $scenario
            ->visit("url('/')")
            ->expect('status_code() == 200')

            ->click('link("Read more")')
            ->expect('status_code() == 200')
        ;

        $player->run($scenario);

.. tip::

    Running more than one scenarios via ``run()`` is safe as the client
    **cookies are cleared at the end of each scenario**.

.. note::

    If your scenarios do not work as expected, :ref:`Enabling Logging
    <player-logging>` might help in getting more information about what's going
    on or use ``-vvv`` to get verbose output with the Player console tool.

Making Requests
---------------

There are several ways you can jump from on HTTP request to the next.

Visiting a Page with ``visit``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``visit`` goes directly to the referenced HTTP URL (defaults to the ``GET``
HTTP method unless you pass one explicitly):

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  method: POST

    .. code-block:: php

        $scenario->visit("url('/blog')", 'POST');

You can also pass the Request body:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  method: PUT
                  body: '{ "title": "New Title" }'

    .. code-block:: php

        $scenario->visit("url('/blog')", 'PUT', '{ "title": "New Title" }');

Clicking on a Link with ``click``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``click`` clicks on a link in an HTML page (takes an expression as an argument):

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - click: link("Add a blog post")

    .. code-block:: php

        // reference a link via the ``link()`` function
        $scenario->click('link("Add a blog post")');

Submitting Forms with ``submit``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``submit`` submits a form in an HTML page (takes an expression as an argument
and an array of values to submit with the form):

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - submit: button("Submit")
                - params:
                    title: scalar('Happy Scraping')
                    content: scalar('Scraping with Blackfire Player is so easy!')

    .. code-block:: php

        // reference a button via the ``button()`` function
        $scenario->submit('button("Submit")', [
            'title' => "'Happy Scraping'",
            'content' => "'Scraping with Blackfire Player is so easy!'",
        ]);

Note that we are using ``scalar()`` for submitted values as they must be
expressions (you can also quote plain strings instead).

Values can also be randomly generated via the ``fake()`` function:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - submit: button("Submit")
                - params:
                    title: fake('sentence', 5)
                    content: join(fake('paragraphs', 3), "\n\n")

    .. code-block:: php

        // reference a button via the ``button()`` function
        $scenario->submit('button("Submit")', [
            'title' => "fake('sentence', 5)",
            'content' => "join(fake('paragraphs', 3), "\n\n")",
        ]);

``fake()`` use the `Faker library <https://github.com/fzaninotto/Faker>`_ under
the hood.

Following Redirections with ``follow``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``follow`` follows a redirection (redirections are never followed automatically
to let you write expectations and assertions on all requests):

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - follow: true

    .. code-block:: php

        $scenario->follow();

Embedding Scenarios with ``add``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``add`` embeds a scenario into another one at any step:

.. configuration-block::

    .. code-block:: yaml
        :emphasize-lines: 1,2,16

        scenarios:
            - options: { key: login }
              steps:
                  - visit: url('/login')
                    expect:
                        - status_code() == 200

                  - submit: button('Login')
                    params:
                        user: scalar('admin')
                        password: scalar('admin')

            - options: { title: "Scenario Name" }
              steps:
                  - add: login

                  - url('/admin')
                    expect:
                        - status_code() == 200

    .. code-block:: php

        use Blackfire\Player\Scenario;

        $loginScenario = new Scenario('Login');
        $loginScenario
            ->visit("url('/login')")
            ->expect('status_code() == 200')

            ->submit('button("Login")', ['user' => "'admin'", 'password' => "'admin'"])
            ->expect('status_code() == 200')
        ;

        $scenario = new Scenario('Scenario Name');
        $scenario
            ->add($loginScenario)

            ->visit("url('/admin')")
            ->expect('status_code() == 200')
        ;

Configuring the Request
-----------------------

Each step can be configured via the following options.

Setting a Header with ``header``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``header`` sets a header:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  headers:
                      Accept-Language: en-US

    .. code-block:: php

        $scenario
            ->visit("url('/')")
            ->header('Accept-Language', 'en-US')
        ;

Setting a User and Password with ``auth``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``auth`` sets the ``Authorization`` header:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  auth: [username, password]

    .. code-block:: php

        $scenario
            ->visit("url('/')")
            ->auth('username', 'password')
        ;

Waiting before Sending with ``delay``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``delay`` adds a delay in milliseconds before sending the request:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  delay: 10000

    .. code-block:: php

        $scenario
            ->visit("url('/')")
            ->delay(10000)
        ;

The ``delay`` value can be any valid expression; get a random delay by using
``fake()``:

.. code-block:: text

    fake('numberBetween', 1000, 3000)

Sending a JSON Body with ``json``
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``json`` configures the Request to upload JSON encoded data as the body:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  method: POST
                  params:
                      foo: bar
                  json: true

    .. code-block:: php

        $scenario
            ->visit("url('/')", 'POST', ['foo': 'bar'])
            ->json()
        ;

Setting Options for all Steps
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can also set some of these options for all steps of a scenario:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            options:
                auth: [username, password]
                headers:
                    Accept-Language: en-US

    .. code-block:: php

        $scenario
            ->auth('username', 'password')
            ->header('Accept-Language', 'en-US')
        ;

... which can be disabled on any given step by setting the value to ``false``:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  headers:
                      Accept-Language: false
                  auth: false

    .. code-block:: php

        $scenario
            ->header('Accept-Language', false)
            ->auth(false)
        ;

Running Multiple Scenarios
--------------------------

Instead of running your scenarios one after the other via ``run()`` calls,
store them in a ``ScenarioSet`` instance and run them via ``runMulti()``:

.. configuration-block::

    .. code-block:: yaml

        scenarios:
            - options: { title: Blog }
              steps:
                  - visit: url('/blog/')
                    title: Blog homepage
                    expect:
                        - status_code() == 200

                    # ...

            - options: { title: "Homepage" }
              steps:
                  - url('/admin')

                  # ...

    .. code-block:: php

        use Blackfire\Player\ScenarioSet;
        use Blackfire\Player\Scenario;

        $scenarios = new ScenarioSet();

        $scenarios->add($scenario = new Scenario('Blog'));
        $scenario
            ->visit("url('/blog/')")
            ->title('Blog homepage')
            ->expect('status_code() == 200')

            // ...
        ;

        $scenarios->add($scenario = new Scenario('Homepage'));
        $scenario
            ->visit("url('/')")

            // ...
        ;

        $results = $player->runMulti($scenarios);

``runMulti()`` returns an array of ``Result`` instances (in the same order as
the scenarios stored in ``ScenarioSet``). Like with ``run()``, each scenario is
run independently from the other ones (cookies are cleared).

.. note::

    When using the Blackfire Player console tool, all scenarios are run.

One benefit of ``runMulti()`` is its ability to **run scenarios in parallel**
when you pass multiple instances of clients to Blackfire Player or use
``--concurrency`` when using the Blackfire Player console tool:

.. configuration-block::

    .. code-block:: bash

        blackfire-player run scenarios.yml --concurrency=3

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

.. configuration-block::

    .. code-block:: yaml

        scenarios:

            # create a login scenario
            - options: { key: login }
              steps:
                  - visit: url('/login')
                    expect:
                        - status_code() == 200

                  - submit: button('Login')
                    params:
                        user: scalar('admin')
                        password: scalar('admin')

            # add a first scenario that needs to be logged-in
            - options: { title: "Blog" }
              steps:
                  - add: login

                  - url('/stats')

                  # ...

            # add a second scenario that needs to be logged-in
            - options: { title: "Homepage" }
              steps:
                  - add: login

                  - url('/admin/')

                  # ...

    .. code-block:: php

        // create a login scenario
        $loginScenario = new Scenario('Login');
        $loginScenario
            ->visit("url('/login')")
            ->expect('status_code() == 200')

            ->submit('button("Login")', ['user' => "'admin'", 'password' => "'admin'"])
            ->expect('status_code() == 200')
        ;

        $scenarios = new ScenarioSet();

        // add a first scenario that needs to be logged-in
        $scenarios->add($scenario = new Scenario('Blog'));
        $scenario
            ->add($loginScenario)
            ->visit("url('/stats/')")

            // ...
        ;

        // add a second scenario that needs to be logged-in
        $scenarios->add($scenario = new Scenario('Homepage'));
        $scenario
            ->add($loginScenario)
            ->visit("url('/admin/')")

            // ...
        ;

        $results = $player->runMulti($scenarios);
