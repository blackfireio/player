Scenarios Formats
=================

Blackfire Player supports several formats to describe the scenarios: PHP
objects, PHP arrays, and YAML.

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
                  - status_code() == 200
                  - header('content_type') matches '/html/'
                  - css('body')

            - title: "Releases"
              click: link('Releases')
              expect:
                  - status_code() == 200
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
                    'status_code() == 200',
                ],
            ],
        ],
    ];

    use Blackfire\Player\Loader\ArrayLoader;

    $loader = new ArrayLoader();
    $scenario = $loader->load($scenario);

The syntax of the array is the same as the YAML structure.
