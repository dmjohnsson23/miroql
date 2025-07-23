======
Miroql
======

.. role:: json(code)
   :language: json

"Miroql" is an acronym for "Mango-inspired report-oriented query language". As the name implies, it 
is heavilly inspired by the "Mango" query language used in 
`Apache CouchDB <https://docs.couchdb.org/en/stable/api/database/find.html>`_. It translates a 
JSON-based query into MySQL syntax, while applying name translation and filtering to present a 
clean interface and to ensure security and permissions are honored.

The basic structure of a Miroql query looks like this:

.. code-block:: json

    {
        "fields":[
            "user.f_name",
            "user.l_name",
            {"$count": "article.id"}
        ],
        "selector":{
            "user":{
                "role":"writer"
            }
        },
        "join": "left"
    }

Which would be equivilent to the following SQL: (Note that the exact SQL will differ depending on 
how the name translators and join conditions are set up in the Miroql engine)

.. code-block:: sql

    SELECT user.f_name, user.l_name, COUNT(article.id)
    FROM user
    LEFT JOIN article ON user.id = article.user_id
    WHERE user.role = 'writer';

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Configuring the Miroql Engine
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

To use Miroql, you must provide an implementation of :php:class:`Translator`. This class 
provides a few imported pieces of key functionality:

* Mapping keys in Miroql queries to real MySQL table and column names
* Describing table relationships and join conditions
* Applying any additional filters to the generated query to enforce permissions

.. warning::

    Although a default implementation is provided at :php:class:`DefaultTranslator`, this is 
    *only* suitable as a reference or test implementation. You *must* implement your own 
    translator, for both security and practicality reasons.

Your translator is then passed to the Miroql engine:

.. code-block:: php

    // Create an engine to process queries (this only needs to be done once in your application)
    $engine = new Miroql(new MyTranslator());
    // Use the engine to create queries
    $query = $engine->makeQuery($miroqlQuery, $baseTable);
    // Query objects can then be converted to SQL and executed using PDO
    $params = [];
    $sql = $query->build($params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // A convenience function can also directly execute the query for you if preferred
    $stmt = $engine->executeQueryPdo($pdo, $miroqlQuery, $baseTable);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

.. note::

    Queries are not built in a way that is compatible with ``mysqli``, due to using named 
    parameters in the generated SQL. You must use PDO to execute the generated SQL.

---------------------
Enabling Placeholders
---------------------

If you wish the engine to allow placeholder parameters, a third parameter can be passed to 
:php:method:`Miroql::makeQuery`, which is either an associative array of parameters, or a closure
that takes a parameter name and returns a value. (Placeholder parameters are explained below under 
"Selectors".)

.. code-block:: php

    $params = [
        'thing1'=>1,
        'thing2'=>2,
    ];
    $query = $engine->makeQuery($miroqlQuery, $baseTable, $params);


-----------------------
Security Considerations
-----------------------

Miroql, by design, allows executing user-controlled queries against your database. However, 
measures are in place to ensure a reasonable level of security. Namely,

1. All table and column names are passed through your translator, offering you the chance to 
   inspect, whitelist, sanitize, or otherwise control what data is actually accessed.
2. Values are passed as prepared statement parameters rather than directly encoded, taking
   advantage of a battle-tested escaping method.
3. Your translator is able to inject additional filters into the generated queries, providing 
   an opportunity for you to enforce your application's access controls.
4. Miroql only generates ``SELECT`` statements.

Thus, so long as your translator is correctly and safely implemented, using Miroql should be 
secure. However, as an added safety measure, you can use a dedicated PDO instance to execute
Miroql queries, which connects to the database using a user with limited permission. Miroql is
also compatible with ``PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false``, which you can enable to reduce
the damage that could be done if a successful SQL injection is achieved. Combining a 
well-implemented translator with both of these secondary precautions should result in solid 
security.

.. warning::

    As this section implies, your translator implementation is crucial to the security of your
    application when using Miroql. Take the time to give it the attention it deserves.

    Best practicies include:

    * Use an explicit list of known and allowed tables and columns
    * Inspect the list of joined tables, and inject access control filters
    * Keep in mind that the untranslated strings do come directly from untrusted input

~~~~~~~~~~~~~~~~~~~~~~
Writing Miroql Queries
~~~~~~~~~~~~~~~~~~~~~~

Miroql queries implement a subset of the SQL ``SELECT`` feature set in JSON. While Miroql does not 
allow explicit join conditions or complex features such as subqueries, it should generally be 
adequate for most reporting needs.

--------------
The Base Table
--------------

Unlike in SQL, the base table for the query is *not* specified in the query itself, but rather as 
an additional parameter to the engine when building or executing the query.

.. code-block:: php

    // Use "user" as the base table
    $query = $engine->makeQuery($miroqlQuery, "user");

Miroql is intended to be used as an API for your application, so it is expected that the base table
would be a URL parameter in the endpoint URL, rather than part of the query.

------
Fields
------

The :json:`"fields"` property is equivilent to the initial ``SELECT`` clause of a ``SELECT`` statement in 
SQL. The items in this array can be either of the following:

1. A name string.

   This string should follow the format :samp:`[table.]column`. For example, :json:`"name"` would 
   select the ``name`` column from the base table, while :json:`"user.name"` would implictly join
   the ``user`` table and select the ``name`` column from it.

2. An aggregate object.

   This object would contain a single property, indicating the aggregate function to use, mapped to
   a name following the same structure as described above. For exmaple, :json:`{"$count":"id}"` 
   would equate to ``COUNT(id)`` in SQL.

There are several aggregate functions which are allowed:

* ``$value``: No aggregate function; equivilent to just using a name string
* ``$count``: Translates to ``COUNT(...)``
* ``$count-distinct``: Translates to ``COUNT(DISTINCT ...)``
* ``$concat``: Translates to ``GROUP_CONCAT(...)``
* ``$concat-distinct``: Translates to ``GROUP_CONCAT(DISTINCT ...)``
* ``$distinct``: Translates to ``DISTINCT ...``
* ``$sum``: Translates to ``SUM(...)``
* ``$avg``: Translates to ``AVG(...)``
* ``$min``: Translates to ``MIN(...)``
* ``$max``: Translates to ``MAX(...)``

An example of a complete :json:`"fields"` array is as follows:

.. code-block:: json

    [
        "user.f_name",
        "user.l_name",
        {"$count": "article.id"}
    ]

---------
Selectors
---------

The :json:`"selector"` property is equivilent to the ``WHERE`` clause of a ``SELECT`` statement in 
SQL. The properties in this object can be either of the following:

1. A name string mapped to a value.

   This is equivilent to a simple equality statement. For example, :json:`{"user.name": "John"}`
   would translate to something like ``user.name = 'John'`` in SQL.

   Placeholders are allowed in values. Placeholders are marked by the ``@`` sign. For example, 
   :json:`{"user.name": "@name"}`. The engine must be configured to take advantage of placeholders,
   as descibed above under "Enabling Placeholders".

   To encode a literal string which begins with an ``@``, double it.  :json:`{"user.name": "@@john"}`
   would translate to ``user.name = '@john'`` instead of representing a placeholder. Escaping is 
   only needed if the first character of the string is an ``@``; any which appear in later 
   positions are always treated as literal.

2. A name string mapped to an operator object.

   This enables the use of operators other than simple equality. For example, 
   :json:`{"date":{"$gt":"2025-02-02"}}` would translate to something like ``date > '2025-02-02'``
   in SQL.

3. A table name mapped to an object of column names, mapped to values or operators.

   This allows :json:`{"user":{"name": "John"}}`, which is identical to :json:`{"user.name": "John"}`.

4. :json:`"$and"` or :json:`"$or"` mapped to a array of nested selector objects.

   This creates a paranthasized group with the specified logical operator when translated. For 
   example, :json:`{"$or": [{"user.name": "John"}, {"user.name": "Sarah"}]}` would translate to
   something like ``(user.name = 'John' OR user.name = 'Sarah')`` in SQL.

5. :json:`"$not"` mapped to a nested selector object.

   This simply adds a ``NOT`` to the SQL generated by the nested object

6. A name string mapped to an `"$and"`, :json:`"$or"`, or :json:`"$not"`.

   This implements the same functionality as described above but allows inverting the order of
   the nesting. This means that :json:`{"user.name": {"$or": [{"$eq": "John"}, {"$eq": "Sarah"}]}}`
   should be identical to :json:`{"$or": [{"user.name": "John"}, {"user.name": "Sarah"}]}`

The following is a list of recognized operators:

* :json:`"$eq"`: Equality
* :json:`"$ne"` / :json:`"$neq"`: Inequality
* :json:`"$lt"`: Less than
* :json:`"$lte"`: Less than or equal
* :json:`"$gt"`: Greater than
* :json:`"$gte"`: Greater than or equal
* :json:`"$in"`: In (an array)
* :json:`"$not-in"`: Not in (an array)
* :json:`"$empty"`: Null or empty string
* :json:`"$not-empty"`: Not null or empty string
* :json:`"$like"`: SQL ``LIKE`` operator
* :json:`"$not-like"`: SQL ``NOT LIKE`` operator
* :json:`"$contains"`: In (a string) / SQL ``LIKE`` operator with a ``%`` before and after
* :json:`"$not-contains"`: Not in (a string) / SQL ``NOT LIKE`` operator with a ``%`` before and after
* :json:`"$regex"`: Regex match
* :json:`"$not-regex"`: Inverted regex match

-------
Sorting
-------

The :json:`"sort"` property is equivilent to SQL's ``ORDER BY`` clause. It can contain any of the 
following:

1. A name string.

   Sorts the results in ascending order by the named column.

2. An object mapping sort direction to a name string.

   Allows you to explicitly control the sort direction. For exmaple, :json:`{"$desc":"date"}` would 
   generate SQL similar to ``ORDER BY date DESC``.

3. An array of either of the above, to allow multi-column sorting.

--------
Grouping
--------

The :json:`"group"` property is equivilent to SQL's ``GROUP BY`` clause. It can contain either of 
the following:

1. A name string.

   Groups the results by the named column.

2. An array of name string, to allow grouping by multiple columns.

----------------
Limiting Results
----------------

The Miroql query may also contain :json:`"skip"` and :json:`"limit"` properties, which are 
equivilent to SQL's ``LIMIT`` clause. Both must be integers. :json:`"limit"` may be used alone,
but :json:`"skip"` may not be used without an accompanying :json:`"limit"`.

-------------------------
Controlling Join Behavior
-------------------------

While joins themselves are always implicit in Miroql queries, you are able to specify a 
:json:`"join"` property, which accepts the values :json:`"inner"`, :json:`"left"`, or 
:json:`"right"`. This allows you to control how tables are joined to the base table.