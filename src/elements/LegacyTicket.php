<?php
namespace verbb\events\elements;

use verbb\events\elements\db\LegacyTicketQuery;

use craft\commerce\base\Purchasable;

class LegacyTicket extends Purchasable
{
    // Static Methods
    // =========================================================================

    public static function find(): LegacyTicketQuery
    {
        return new LegacyTicketQuery(static::class);
    }

}
