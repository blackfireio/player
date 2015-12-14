:access: ROLE_ADMIN

Scraping Values
===============

When crawling an HTTP application you can extract values from HTTP responses:

.. code-block:: php

    $scenario
        ->visit(url('/blog/'))
        ->expect('status_code() == 200')
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
        ->visit(url('/blog/'))
        ->expect('status_code() == 200')
        ->expect('css(".copyright_year") matches /current_year/')
    ;

    $player->run($scenario);

Variables can be used to **conditionally execute scenarios** based on some
values:

.. code-block:: php

    $scenario = new Scenario();
    $scenario
        ->visit(url('/blog/'))
        ->expect('status_code() == 200')
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
        ->expect('status_code() == 200')
        ->extract('sql_queries', 'json("arguments.\"sql.pdo.queries\".keys(@)")')
        ->extract('store_url', 'json("_links.store.href")')

        ->visit('url(store_url)', 'POST', '{ "foo": "batman" }')
        ->expect('status_code() == 202')
    ;

    $player->run($scenario);
