Scraping Values
===============

When crawling an HTTP application you can extract values from HTTP responses:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            steps:
                - visit: url('/')
                  expect:
                      - status_code() == 200
                  extract:
                      latest_post_title: css(".post h2").first()
                      latest_post_href: css(".post h2 a").first().attr("href")
                      latest_posts: css(".post h2 a").extract(["_text", "href"])
                      age: header("Age")
                      content_type: header("Content-Type")
                      token: regex('/name="_token" value="([^"]+)"/')

    .. code-block:: php

        $scenario
            ->visit("url('/blog/')")
            ->expect('status_code() == 200')
            ->extract('latest_post_title', 'css(".post h2").first()')
            ->extract('latest_post_href', 'css(".post h2 a").first().attr("href")')
            ->extract('latest_posts', 'css(".post h2 a").extract(["_text", "href"])'
            ->extract('age', 'header("Age")')
            ->extract('content_type', 'header("Content-Type")')
            ->extract('token', 'regex(\'/name="_token" value="([^"]+)"/\')')
        ;

The ``extract()`` method takes three arguments:

* The name of the variable you want to store the extracted value in;

* An expression to evaluate (the value of the evaluated expression).

Using ``json()``, ``css()``, and ``xpath()`` on JSON, HTML, and XML responses
is recommended, but for pure text responses or complex extractions, you can use
the generic ``regex()`` function. ``regex()`` takes a regex as an argument an
returns the first match.

The extracted values are also available at the end of a crawling session:

.. configuration-block::

    .. code-block:: bash

        # use --output to store extracted values
        blackfire-player run scenario.yml --output values.json

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
        ->visit("url('/blog/')")
        ->expect('status_code() == 200')
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
        ->visit("url('/blog/')")
        ->expect('status_code() == 200')
        ->expect('css(".copyright_year") matches /current_year/')
    ;

    $player->run($scenario);

Variables can be used to **conditionally execute scenarios** based on some
values:

.. code-block:: php

    $scenario = new Scenario();
    $scenario
        ->visit("url('/blog/')")
        ->expect('status_code() == 200')
        ->extract('post_url', 'css(".posts").attr("href")')
    ;

    $result = $player->run($scenario);

    if ($result['post_url']) {
        $player->run($anotherScenario);
    }

Here is another example for a JSON API:

.. configuration-block::

    .. code-block:: yaml

        scenario:
            options:
                title: Scenario title
                auth: [api_username, api_password]
                variables:
                    profile_uuid: zzzz

            steps:
                - visit: url('/profiles' ~ profile_uuid)
                  expect:
                      - status_code() == 200
                  extract:
                      sql_queries: json('arguments."sql.pdo.queries".keys(@)')
                      store_url: json("_links.store.href")

                - visit: url(store_url)
                  method: POST
                  body: '{ "foo": "batman" }'
                  expect:
                      - status_code() == 200

    .. code-block:: php

        $scenario = new Scenario('Scenario title', [
            'profile_uuid' => 'zzzz',
        ]);

        $scenario
            ->auth('api_username', 'api_password')

            ->visit("url('profiles/' ~ profile_uuid)")
            ->expect('status_code() == 200')
            ->extract('sql_queries', 'json("arguments.\"sql.pdo.queries\".keys(@)")')
            ->extract('store_url', 'json("_links.store.href")')

            ->visit('url(store_url)', 'POST', '{ "foo": "batman" }')
            ->expect('status_code() == 202')
        ;

        $player->run($scenario);
