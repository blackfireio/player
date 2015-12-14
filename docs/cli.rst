Running Scenarios from the Console
----------------------------------

YAML scenarios can be run from the command line via the ``blackfire-player``
utility:

.. code-block:: bash

    ./vendor/bin/blackfire-player run scenario.yml --team=TEAM_NAME_OR_UUID

Use the ``--endpoint`` option to override the endpoint defined in the scenarios:

.. code-block:: bash

    ./vendor/bin/blackfire-player scenario.yml --team=TEAM_NAME_OR_UUID --endpoint=http://example.com/

Use the ``--concurrency`` option to run scenarios in parallel:

.. code-block:: bash

     ./vendor/bin/blackfire-player scenario.yml --team=TEAM_NAME_OR_UUID --concurrency=5

Use the ``--output`` option to save extracted values as a JSON file:

.. code-block:: bash

    ./vendor/bin/blackfire-player scenario.yml --team=TEAM_NAME_OR_UUID --output=values.json

Use the ``--blackire`` to enable Blackfire:

.. code-block:: bash

     ./vendor/bin/blackfire-player scenario.yml --team=TEAM_NAME_OR_UUID --blackire

Use ``-vv`` or ``-vvv`` to get logs about the progress of the player.

The command returns 1 if at least one scenario fails.
