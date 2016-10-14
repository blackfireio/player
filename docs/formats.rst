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
            name: Blackfire Player Scenario
            auth: [foo, bar]
            variables:
                foo: bar

        steps:
            - name: "Blog Homepage"
              visit: url('/blog/')
              assert:
                  - main.peak_memory < 10M
              samples: 2
              name: Blog homepage
              headers:
                  'Accept-Language': 'en-US'
              expect:
                  - status_code() == 200
                  - header('content_type') matches '/html/'
                  - css('body')

            - name: "Releases"
              click: link('Releases')
              expect:
                  - status_code() == 200
                  - css('body.releases')
              extract:
                  latest_release_title: css(".post h2 a").first()
                  latest_release_href: css(".post h2 a").first().extract('href')
                  latest_releases: css(".post h2 a").extract(['_text', 'href'])

            - name: "Latest Release Blog Post"
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
                - name: "Homepage"
                  visit: url('/')

        -
            options:
                name: 'Symfony Blog'
                auth: [foo, bar]

            steps:
                # embed the 'home' scenario
                - add: home

                - name: "Blog Homepage"
                  visit: url('/blog/')

                - name: "JSON API"
                  visit: url('/api')
                  method: POST
                  params:
                      foo: scalar('bar')
                  json: true

Note that scenarios defined with a key are abstract and will not be run by
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
                'name' => 'Blog Homepage',
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
