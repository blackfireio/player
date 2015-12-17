Running Scenarios from the Console
----------------------------------

YAML scenarios can be run from the command line via the ``blackfire-player``
utility (see :doc:`installation </player/installation>` for download
instructions):

.. code-block:: bash

    ./vendor/bin/blackfire-player run scenario.yml

Use the ``--endpoint`` option to override the endpoint defined in the scenarios:

.. code-block:: bash

    ./vendor/bin/blackfire-player scenario.yml --endpoint=http://example.com/

Use the ``--concurrency`` option to run scenarios in parallel:

.. code-block:: bash

     ./vendor/bin/blackfire-player scenario.yml --concurrency=5

Use the ``--output`` option to save extracted values as a JSON file:

.. code-block:: bash

    ./vendor/bin/blackfire-player scenario.yml --output=values.json

Use the ``--variables`` option to override variable values:

.. code-block:: bash

     ./vendor/bin/blackfire-player scenario.yml --variables="foo=bar" --variables="bar=foo"

Use the ``--blackfire`` option to enable Blackfire:

.. code-block:: bash

     ./vendor/bin/blackfire-player scenario.yml --blackfire=ENV_NAME_OR_UUID

Use ``-vv`` or ``-vvv`` to get logs about the progress of the player.

The command returns 1 if at least one scenario fails.
