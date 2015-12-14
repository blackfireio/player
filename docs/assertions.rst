:access: ROLE_ADMIN

Writing Blackfire Assertions
============================

Blackfire Player natively supports Blackfire:

.. code-block:: php

    use Blackfire\Client as BlackfireClient;
    use Blackfire\ClientConfiguration;

    $player = new Player($client);

    // enable the Blackfire extension
    $config = (new ClientConfiguration())->setEnv('Env name');
    $blackfire = new BlackfireClient($config);

    $player->addExtension(new \Blackfire\Player\Extension\BlackfireExtension($blackfire, $logger));

When running a scenario, Blackfire creates a build that contains all profiles
and assertion reports for requests made in the executed scenario; the scenario
name is then used as the build name:

.. code-block:: php

    $scenario = new Scenario('Scenario Name');

When Blackfire support is enabled, the assertions defined in ``.blackfire.yml``
are automatically run along side expectations.

Additional features are also automatically activated:

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

.. caution::

    This ``assert()`` feature is not supported yet.
