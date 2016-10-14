Scraping Values
===============

When crawling an HTTP application you can extract values from HTTP responses:

.. configuration-block::

    .. code-block:: yaml

        scenario
            visit url('/')
                expect status_code() == 200
                set latest_post_title css(".post h2").first()
                set latest_post_href css(".post h2 a").first().attr("href")
                set latest_posts css(".post h2 a").extract('_text', 'href'])
                set age header("Age")
                set content_type header("Content-Type")
                set token regex('/name="_token" value="([^"]+)"/')

    .. code-block:: php

        $scenario
            ->visit("url('/blog/')")
            ->expect('status_code() == 200')
            ->set('latest_post_title', 'css(".post h2").first()')
            ->set('latest_post_href', 'css(".post h2 a").first().attr("href")')
            ->set('latest_posts', 'css(".post h2 a").extract(["_text", "href"])'
            ->set('age', 'header("Age")')
            ->set('content_type', 'header("Content-Type")')
            ->set('token', 'regex(\'/name="_token" value="([^"]+)"/\')')
        ;

The ``set()`` method takes three arguments:

* The name of the variable you want to store the value in;

* An expression to evaluate (the value of the evaluated expression).

Using ``json()``, ``css()``, and ``xpath()`` on JSON, HTML, and XML responses
is recommended, but for pure text responses or complex values, you can use the
generic ``regex()`` function. ``regex()`` takes a regex as an argument an
returns the first match.

The values are also available at the end of a crawling session:

.. configuration-block::

    .. code-block:: bash

        # use --json to display variable values
        blackfire-player run scenario.yml --json

    .. code-block:: php

        $result = $player->run($scenario);
        $value = $result['latest_post_title'];

        // get all values
        $values = $result->getValues();

        // iterate over all values
        foreach ($result as $key => $value) {
            // ...
        }

Variables can be used in expressions for subsequent requests via the as regular
expression variables:

.. code-block:: php

    $scenario
        ->visit("url('/blog/')")
        ->expect('status_code() == 200')
        ->expect('css(".posts")')
        ->set('latest_post_title', 'css(".post h2 a").first()')

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

    $scenario = new Scenario('Scenario Name', ['current_year' => 2016]);
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
        ->set('post_url', 'css(".posts").attr("href")')
    ;

    $result = $player->run($scenario);

    if ($result['post_url']) {
        $player->run($anotherScenario);
    }

Here is another example for a JSON API:

.. configuration-block::

    .. code-block:: yaml

        scenario
            name "Scenario name"
            auth api_username ~ ':' ~ api_password
            set profile_uuid zzzz

            visit url('/profiles' ~ profile_uuid)
                expect status_code() == 200
                set sql_queries json('arguments."sql.pdo.queries".keys(@)')
                set store_url json("_links.store.href")

            visit url(store_url)
                method POST
                body '{ "foo": "batman" }'
                expect status_code() == 200

    .. code-block:: php

        $scenario = new Scenario('Scenario name', [
            'profile_uuid' => 'zzzz',
        ]);

        $scenario
            ->auth('api_username', 'api_password')

            ->visit("url('profiles/' ~ profile_uuid)")
            ->expect('status_code() == 200')
            ->set('sql_queries', 'json("arguments.\"sql.pdo.queries\".keys(@)")')
            ->set('store_url', 'json("_links.store.href")')

            ->visit('url(store_url)', 'POST', '{ "foo": "batman" }')
            ->expect('status_code() == 202')
        ;

        $player->run($scenario);
