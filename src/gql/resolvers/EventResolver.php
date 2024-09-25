<?php
namespace verbb\events\gql\resolvers;

use verbb\events\elements\Event;
use verbb\events\helpers\Gql as GqlHelper;
use verbb\events\helpers\Table;

use craft\elements\db\ElementQuery;
use craft\gql\base\ElementResolver;
use craft\helpers\Db;

use Illuminate\Support\Collection;

class EventResolver extends ElementResolver
{
    // Static Methods
    // =========================================================================

    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        if ($source === null) {
            $query = Event::find();
        } else {
            $query = $source->$fieldName;
        }

        if (!$query instanceof ElementQuery) {
            return $query;
        }

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        $pairs = GqlHelper::extractAllowedEntitiesFromSchema();

        if (!GqlHelper::canQueryEvents()) {
            return [];
        }

        $query->andWhere(['in', 'typeId', array_values(Db::idsByUids('{{%events_event_types}}', $pairs['eventsEventTypes']))]);

        return $query;
    }
}
