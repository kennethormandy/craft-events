# Single Event
Once you've got a list of events, you'll want to allow your customers to drill-into a single event page for more detail. This takes many cues from the single Product page for Commerce.

You'll have access to a `event` variable, which represents the single event you're looking at. You can also interchangeably use `product` if you wish.

```twig
<h1>{{ event.title }}</h1>

{% if event.isAvailable %}
    <form method="POST">
        {{ actionInput('commerce/cart/update-cart') }}
        {{ csrfInput() }}

        <input type="number" name="qty" value="1">

        <select name="purchasableId">
            {% for ticket in event.getTickets() %}
                <option value="{{ ticket.id }}" {% if not ticket.isAvailable %}disabled{% endif %}>
                    {{ ticket.title }} - {{ ticket.price | commerceCurrency(cart.currency) }}
                </option>
            {% endfor %}
        </select>

        <button type="submit">Add to cart</button>
    </form>
{% else %}
    <strong>Sold out</strong>
{% endif %}
```

The above shows all tickets for an event, whether they're available or not. We use `ticket.isAvailable` to see if we can purchase this ticket, as it could be sold out.

If you wanted to only show the available tickets, and not show sold out ones, you could loop through `event.availableTickets()` instead.

```twig
<select>
    {%- for ticket in event.availableTickets() -%}
        <option value="{{ ticket.id }}">
            {{ ticket.title }} - {{ ticket.price | commerceCurrency(cart.currency) }}
        </option>
    {% endfor %}
</select>
```

In addition, the check for `event.isAvailable` checks whether all tickets are sold out (or unavailable), and if so, will show a 'Sold Out' notice, that no tickets for this event are available.

## Adding tickets to your cart
Adding a ticket to your cart works in very much the same way as [Craft Commerce](https://docs.craftcms.com/commerce/v3/adding-to-and-updating-the-cart.html):

```twig
<form method="POST">
    {{ actionInput('commerce/cart/update-cart') }}
    {{ csrfInput() }}

    <input type="number" name="qty" value="1">

    <select name="purchasableId">
        {% for ticket in event.getTickets() %}
            <option value="{{ ticket.id }}" {% if not ticket.isAvailable %}disabled{% endif %}>
                {{ ticket.title }} - {{ ticket.price | commerceCurrency(cart.currency) }}
            </option>
        {% endfor %}
    </select>

    <button type="submit">Add to cart</button>
</form>
```

### Line item options
You can also set additional data through [line item options](https://docs.craftcms.com/commerce/v3/adding-to-and-updating-the-cart.html#line-item-options-and-notes). These values can be whatever you like, and very flexible.

```twig
<form method="POST">
    {{ actionInput('commerce/cart/update-cart') }}
    {{ csrfInput() }}

    <input type="number" name="qty" value="1">

    <select name="purchasableId">
        {% for ticket in event.getTickets() %}
            <option value="{{ ticket.id }}" {% if not ticket.isAvailable %}disabled{% endif %}>
                {{ ticket.title }} - {{ ticket.price | commerceCurrency(cart.currency) }}
            </option>
        {% endfor %}
    </select>

    <input type="text" name="options[isVip]" value="Yes">
    <input type="text" name="options[includeSwagBag]" value="Yes">
    <input type="text" name="options[numberOfExtraGuests]" value="3">

    <button type="submit">Add to cart</button>
</form>
```

If you have any custom field setup on your ticket types, you can also set content on those fields through your line item options, and they'll be automatically 'pushed' to the resulting [purchased ticket](docs:developers/purchased-ticket).

For example, let's say you have a 'Child' ticket type setup, with a custom field with the handle `age`. In this instance, you want to include additional information such as the child's age when buying tickets. You include this field in your add-to-cart form:

```twig
<form method="POST">
    {{ actionInput('commerce/cart/update-cart') }}
    {{ csrfInput() }}

    ...

    <input type="text" name="options[age]" value="16">

    <button type="submit">Add to cart</button>
</form>
```

After checkout has been completed, any line item option that matches a custom field on your ticket type will be copied across to the resulting [purchased ticket](docs:developers/purchased-ticket). You'll be able to easily see the values as provided in the control panel, when viewing your purchased tickets.

