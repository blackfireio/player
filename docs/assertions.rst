Writing Blackfire Assertions
============================

Blackfire Player natively supports Blackfire:

.. configuration-block::

    .. code-block:: bash

        blackfire-player run scenario.yml --blackfire=ENV_NAME_OR_UUID

    .. code-block:: php

        use Blackfire\Client as BlackfireClient;
        use Blackfire\ClientConfiguration;

        $player = new Player($client);

        // enable the Blackfire extension
        $config = (new ClientConfiguration())->setEnv('ENV_NAME_OR_UUID');
        $blackfire = new BlackfireClient($config);

        $player->addExtension(new \Blackfire\Player\Extension\BlackfireExtension($blackfire, $logger));

When running a scenario, Blackfire creates a build that contains all profiles
and assertion reports for requests made in the executed scenario; the scenario
name is then used as the build name:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            options:
                title: Scenario Name

    .. code-block:: php

        $scenario = new Scenario('Scenario Name');

.. note::

    You can set the ``external_id`` and ``external_parent_id`` settings of the
    build by passing environment variables:

    .. code-block:: bash

        BLACKFIRE_BUILD_REFERENCE_ID=ref BLACKFIRE_BUILD_REFERENCE_PARENT_ID=parent \
        blackfire-player run scenario.yml --blackfire=ENV_NAME_OR_UUID

When Blackfire support is enabled, the assertions defined in ``.blackfire.yml``
are automatically run along side expectations.

Additional features are also automatically activated:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/blog/')
                  title: Blog homepage
                  assert:
                      - main.peak_memory < 10M
                  samples: 2

    .. code-block:: php

        $scenario
            ->visit("url('/blog/')")

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

.. configuration-block::

    .. code-block:: yaml

        scenario:
            options:
                variables:
                    env: prod

            steps:
                # no Twig template compilation in production
                # not enforced in other environments
                - visit: url('/blog/')
                  assert:
                      - "prod" == env and metrics.twig.compile.count == 0

    .. code-block:: php

        $scenario
            ->value('env', 'prod')

            // no Twig template compilation in production
            // not enforced on other environments
            ->visit("url('/blog/')")
            ->assert('"prod" == env and metrics.twig.compile.count == 0')
        ;

        $player->run($scenario);

.. caution::

    The ``assert()`` feature is **not supported yet**.
