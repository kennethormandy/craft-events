# Purchased Ticket Queries

You can fetch purchased tickets in your templates or PHP code using **purchased ticket queries**.

:::code
```twig Twig
{# Create a new purchased ticket query #}
{% set myQuery = craft.events.purchasedTickets() %}
```

```php PHP
// Create a new purchased ticket query
$myQuery = \verbb\events\elements\PurchasedTicket::find();
```
:::

Once you’ve created a purchased tickets query, you can set parameters on it to narrow down the results, and then execute it by calling `.all()`. An array of [PurchasedTicket](docs:developers/purchased-ticket) objects will be returned.

:::tip
See Introduction to [Element Queries](https://craftcms.com/docs/4.x/element-queries/) in the Craft docs to learn about how element queries work.
:::

## Example
We can display purchased tickets for a given event by doing the following:

1. Create a purchased ticket query with `craft.events.purchasedTickets()`.
2. Set the [eventId](#eventId) and [limit](#limit) parameters on it.
3. Fetch all purchased tickets with `.all()` and output.
4. Loop through the purchased tickets using a [for](https://twig.symfony.com/doc/2.x/tags/for.html) tag to output the contents.

```twig
{# Create a purchased tickets query with the 'type' and 'limit' parameters #}
{% set purchasedTicketsQuery = craft.events.purchasedTickets()
    .eventId(123)
    .limit(10) %}

{# Fetch the PurchasedTickets #}
{% set purchasedTickets = purchasedTicketsQuery.all() %}

{# Display their contents #}
{% for purchasedTicket in purchasedTickets %}
    <p>{{ purchasedTicket.title }}</p>
{% endfor %}
```

## Parameters

Purchased Ticket queries support the following parameters:

<!-- BEGIN PARAMS -->

### `asArray`

Causes the query to return matching purchased tickets as arrays of data, rather than [PurchasedTicket](docs:developers/purchased-ticket) objects.

::: code
```twig Twig
{# Fetch purchased tickets as arrays #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .asArray()
    .all() %}
```

```php PHP
// Fetch purchased tickets as arrays
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->asArray()
    ->all();
```
:::



### `customer`

Narrows the query results to only purchased tickets that have been purchased by a customer.

::: code
```twig Twig
{# Fetch purchased tickets that have been purchased by the current user #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .customer(currentUser)
    .all() %}
```

```php PHP
// Fetch purchased tickets that have been purchased by a customer
$currentUser = Craft::$app->getUser()->getIdentity();

$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->customer($currentUser)
    ->all();
````
:::



### `dateCreated`

Narrows the query results based on the purchased tickets creation dates.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `'>= 2018-04-01'` | that were created on or after 2018-04-01.
| `'< 2018-05-01'` | that were created before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were created between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch purchased tickets created last month #}
{% set start = date('first day of last month') | atom %}
{% set end = date('first day of this month') | atom %}

{% set purchasedTickets = craft.events.purchasedTickets()
    .dateCreated(['and', ">= #{start}", "< #{end}"])
    .all() %}
```

```php PHP
// Fetch purchased tickets created last month
$start = new \DateTime('first day of next month')->format(\DateTime::ATOM);
$end = new \DateTime('first day of this month')->format(\DateTime::ATOM);

$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->dateCreated(['and', ">= {$start}", "< {$end}"])
    ->all();
```
:::



### `dateUpdated`

Narrows the query results based on the purchased tickets last-updated dates.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `'>= 2018-04-01'` | that were updated on or after 2018-04-01.
| `'< 2018-05-01'` | that were updated before 2018-05-01
| `['and', '>= 2018-04-04', '< 2018-05-01']` | that were updated between 2018-04-01 and 2018-05-01.

::: code
```twig Twig
{# Fetch purchased tickets updated in the last week #}
{% set lastWeek = date('1 week ago')|atom %}

{% set purchasedTickets = craft.events.purchasedTickets()
    .dateUpdated(">= #{lastWeek}")
    .all() %}
```

```php PHP
// Fetch purchased tickets updated in the last week
$lastWeek = new \DateTime('1 week ago')->format(\DateTime::ATOM);

$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->dateUpdated(">= {$lastWeek}")
    ->all();
```
:::



### `eventId`

Narrows the query results based on the purchased tickets' event ID.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | for a event with an ID of 1.
| `[1, 2]` | for event with an ID of 1 or 2.
| `['not', 1, 2]` | for event not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch purchased tickets for an event #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .eventId(123)
    .all() %}
```

```php PHP
// Fetch purchased tickets for an event
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->eventId(123)
    ->all();
```
:::



### `fixedOrder`

Causes the query results to be returned in the order specified by [id](#id).

::: code
```twig Twig
{# Fetch purchased tickets in a specific order #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .id([1, 2, 3, 4, 5])
    .fixedOrder()
    .all() %}
```

```php PHP
// Fetch purchased tickets in a specific order
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->id([1, 2, 3, 4, 5])
    ->fixedOrder()
    ->all();
```
:::



### `id`

Narrows the query results based on the purchased tickets IDs.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | with an ID of 1.
| `'not 1'` | not with an ID of 1.
| `[1, 2]` | with an ID of 1 or 2.
| `['not', 1, 2]` | not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch the purchased ticket by its ID #}
{% set purchasedTicket = craft.events.purchasedTickets()
    .id(1)
    .one() %}
```

```php PHP
// Fetch the purchased ticket by its ID
$purchasedTicket = \verbb\events\elements\PurchasedTicket::find()
    ->id(1)
    ->one();
```
:::

::: tip
This can be combined with [fixedOrder](#fixedorder) if you want the results to be returned in a specific order.
:::



### `inReverse`

Causes the query results to be returned in reverse order.

::: code
```twig Twig
{# Fetch purchased tickets in reverse #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .inReverse()
    .all() %}
```

```php PHP
// Fetch purchased tickets in reverse
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->inReverse()
    ->all();
```
:::



### `limit`

Determines the number of purchased tickets that should be returned.

::: code
```twig Twig
{# Fetch up to 10 purchased tickets #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .limit(10)
    .all() %}
```

```php PHP
// Fetch up to 10 purchased tickets
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->limit(10)
    ->all();
```
:::



### `lineItemId`

Narrows the query results based on the purchased tickets Commerce line item ID.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | for a Commerce line item with an ID of 1.
| `[1, 2]` | for Commerce line item with an ID of 1 or 2.
| `['not', 1, 2]` | for Commerce line item not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch purchased tickets for a Commerce line item #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .lineItemId(123)
    .all() %}
```

```php PHP
// Fetch purchased tickets for a Commerce line item
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->lineItemId(123)
    ->all();
```
:::



### `offset`

Determines how many purchased tickets should be skipped in the results.

::: code
```twig Twig
{# Fetch all purchased tickets except for the first 3 #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .offset(3)
    .all() %}
```

```php PHP
// Fetch all purchased tickets except for the first 3
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->offset(3)
    ->all();
```
:::



### `orderId`

Narrows the query results based on the purchased tickets Commerce order ID.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | for a Commerce order with an ID of 1.
| `[1, 2]` | for Commerce order with an ID of 1 or 2.
| `['not', 1, 2]` | for Commerce order not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch purchased tickets for a Commerce order #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .orderId(123)
    .all() %}
```

```php PHP
// Fetch purchased tickets for a Commerce order
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->orderId(123)
    ->all();
```
:::



### `orderBy`

Determines the order that the purchased tickets should be returned in.

::: code
```twig Twig
{# Fetch all purchased tickets in order of date created #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .orderBy('elements.dateCreated asc')
    .all() %}
```

```php PHP
// Fetch all purchased tickets in order of date created
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->orderBy('elements.dateCreated asc')
    ->all();
```
:::



### `relatedTo`

Narrows the query results to only purchased tickets that are related to certain other elements.

See [Relations](https://craftcms.com/docs/4.x/relations.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Fetch all purchased tickets that are related to myCategory #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .relatedTo(myCategory)
    .all() %}
```

```php PHP
// Fetch all purchased tickets that are related to $myCategory
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->relatedTo($myCategory)
    ->all();
```
:::



### `ticketId`

Narrows the query results based on the purchased tickets ticket ID.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | for a ticket with an ID of 1.
| `[1, 2]` | for ticket with an ID of 1 or 2.
| `['not', 1, 2]` | for ticket not with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch purchased tickets for a ticket #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .ticketId(123)
    .all() %}
```

```php PHP
// Fetch purchased tickets for a ticket
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->ticketId(123)
    ->all();
```
:::



### `ticketType`

Narrows the query results based on the purchased tickets ticket type.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `'foo'` | of a ticket type with a handle of `foo`.
| `'not foo'` | not of a ticket type with a handle of `foo`.
| `['foo', 'bar']` | of a ticket type with a handle of `foo` or `bar`.
| `['not', 'foo', 'bar']` | not of a ticket type with a handle of `foo` or `bar`.

::: code
```twig Twig
{# Fetch purchased tickets for a ticket type #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .ticketType('adult')
    .all() %}
```

```php PHP
// Fetch purchased tickets for a ticket
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->ticketType('adult')
    ->all();
```
:::



### `typeId`

Narrows the query results based on the purchased tickets ticket type IDs.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `1` | of a ticket type with an ID of 1.
| `'not 1'` | not of a ticket type with an ID of 1.
| `[1, 2]` | of a ticket type with an ID of 1 or 2.
| `['not', 1, 2]` | not of a ticket type with an ID of 1 or 2.

::: code
```twig Twig
{# Fetch purchased tickets for a ticket type #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .ticketTypeId(1)
    .all() %}
```

```php PHP
// Fetch events of the event type with an ID of 1
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->ticketTypeId(1)
    ->all();
```
:::



### `search`

Narrows the query results to only purchased tickets that match a search query.

See [Searching](https://craftcms.com/docs/4.x/searching.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Get the search query from the 'q' query string param #}
{% set searchQuery = craft.app.request.getQueryParam('q') %}

{# Fetch all purchased tickets that match the search query #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .search(searchQuery)
    .all() %}
```

```php PHP
// Get the search query from the 'q' query string param
$searchQuery = \Craft::$app->getRequest()->getQueryParam('q');

// Fetch all purchased tickets that match the search query
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->search($searchQuery)
    ->all();
```
:::



### `site`

Determines which site the purchased tickets should be queried in.

The current site will be used by default.

Possible values include:

| Value | Fetches purchased tickets…
| - | -
| `'foo'` | from the site with a handle of `foo`.
| a `\craft\commerce\elements\db\Site` object | from the site represented by the object.

::: code
```twig Twig
{# Fetch purchased tickets from the Foo site #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .site('foo')
    .all() %}
```

```php PHP
// Fetch purchased tickets from the Foo site
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->site('foo')
    ->all();
```
:::



### `siteId`

Determines which site the purchased tickets should be queried in, per the site’s ID.

The current site will be used by default.

::: code
```twig Twig
{# Fetch purchased tickets from the site with an ID of 1 #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .siteId(1)
    .all() %}
```

```php PHP
// Fetch purchased tickets from the site with an ID of 1
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->siteId(1)
    ->all();
```
:::



### `uid`

Narrows the query results based on the purchased tickets UIDs.

::: code
```twig Twig
{# Fetch the purchased ticket by its UID #}
{% set purchasedTicket = craft.events.purchasedTickets()
    .uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    .one() %}
```

```php PHP
// Fetch the purchased ticket by its UID
$purchasedTicket = \verbb\events\elements\PurchasedTicket::find()
    ->uid('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
    ->one();
```
:::



### `with`

Causes the query to return matching purchased tickets eager-loaded with related elements.

See [Eager-Loading Elements](https://craftcms.com/docs/4.x/eager-loading-elements.html) for a full explanation of how to work with this parameter.

::: code
```twig Twig
{# Fetch purchased tickets eager-loaded with the "Related" field’s relations #}
{% set purchasedTickets = craft.events.purchasedTickets()
    .with(['related'])
    .all() %}
```

```php PHP
// Fetch purchased tickets eager-loaded with the "Related" field’s relations
$purchasedTickets = \verbb\events\elements\PurchasedTicket::find()
    ->with(['related'])
    ->all();
```
:::


<!-- END PARAMS -->
