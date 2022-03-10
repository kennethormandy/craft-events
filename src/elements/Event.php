<?php
namespace verbb\events\elements;

use verbb\events\Events;
use verbb\events\elements\db\EventQuery;
use verbb\events\helpers\EventHelper;
use verbb\events\helpers\TicketHelper;
use verbb\events\models\EventType;
use verbb\events\records\EventRecord;

use Craft;
use craft\base\Element;
use craft\db\Query;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\validators\DateTimeValidator;

use yii\base\Exception;
use yii\base\InvalidConfigException;

use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Description\Location;

use DateTime;
use DateTimeZone;

class Event extends Element
{
    // Constants
    // =========================================================================

    public const STATUS_LIVE = 'live';
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';

    
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('events', 'Event');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('events', 'Events');
    }

    public static function refHandle(): ?string
    {
        return 'event';
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('app', 'Live'),
            self::STATUS_PENDING => Craft::t('app', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('app', 'Expired'),
            self::STATUS_DISABLED => Craft::t('app', 'Disabled'),
        ];
    }

    public static function find(): EventQuery
    {
        return new EventQuery(static::class);
    }

    public static function eagerLoadingMap(array $sourceElements, string $handle): array|null|false
    {
        if ($handle == 'tickets') {
            $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

            $map = (new Query())
                ->select('eventId as source, id as target')
                ->from(['{{%events_tickets}}'])
                ->where(['in', 'eventId', $sourceElementIds])
                ->orderBy('sortOrder asc')
                ->all();

            return [
                'elementType' => Ticket::class,
                'map' => $map,
            ];
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    public static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute): void
    {
        if ($attribute === 'tickets') {
            $with = $elementQuery->with ?: [];
            $with[] = 'tickets';
            $elementQuery->with = $with;
        } else {
            parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
        }
    }

    protected static function defineSources(string $context = null): array
    {
        if ($context == 'index') {
            $eventTypes = Events::$plugin->getEventTypes()->getEditableEventTypes();
            $editable = true;
        } else {
            $eventTypes = Events::$plugin->getEventTypes()->getAllEventTypes();
            $editable = false;
        }

        $eventTypeIds = [];

        foreach ($eventTypes as $eventType) {
            $eventTypeIds[] = $eventType->id;
        }

        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('events', 'All events'),
                'criteria' => [
                    'typeId' => $eventTypeIds,
                    'editable' => $editable,
                ],
                'defaultSort' => ['postDate', 'desc'],
            ],
        ];

        $sources[] = ['heading' => Craft::t('events', 'Event Types')];

        foreach ($eventTypes as $eventType) {
            $key = 'eventType:' . $eventType->uid;
            $canEditEvents = Craft::$app->getUser()->checkPermission('events-manageEventType:' . $eventType->uid);

            $sources[] = [
                'key' => $key,
                'label' => $eventType->name,
                'data' => [
                    'handle' => $eventType->handle,
                    'editable' => $canEditEvents,
                ],
                'criteria' => [
                    'typeId' => $eventType->id,
                    'editable' => $editable,
                ],
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('events', 'Are you sure you want to delete the selected events?'),
            'successMessage' => Craft::t('events', 'Events deleted.'),
        ]);

        $actions[] = [
            'type' => Duplicate::class,
        ];

        return $actions;
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'sku'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'startDate' => Craft::t('events', 'Start Date'),
            'endDate' => Craft::t('events', 'End Date'),
            'postDate' => Craft::t('app', 'Post Date'),
            'expiryDate' => Craft::t('app', 'Expiry Date'),
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Title')],
            'type' => ['label' => Craft::t('app', 'Type')],
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'startDate' => ['label' => Craft::t('events', 'Start Date')],
            'endDate' => ['label' => Craft::t('events', 'End Date')],
            'postDate' => ['label' => Craft::t('app', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('app', 'Expiry Date')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }


    // Properties
    // =========================================================================

    public ?bool $allDay = null;
    public ?int $capacity = null;
    public ?DateTime $endDate = null;
    public ?DateTime $expiryDate = null;
    public ?int $id = null;
    public ?DateTime $postDate = null;
    public ?DateTime $startDate = null;
    public ?int $typeId = null;

    private ?EventType $_eventType = null;
    private ?array $_tickets = null;


    // Public Methods
    // =========================================================================

    public function __toString(): string
    {
        return (string)$this->title;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['typeId'], 'number', 'integerOnly' => true];
        $rules[] = [['startDate', 'endDate', 'postDate', 'expiryDate'], DateTimeValidator::class];

        $rules[] = [
            ['tickets'], function($model): void {
                foreach ($this->getTickets() as $ticket) {
                    // Break immediately if no ticket type set, also check for other ticket validation errors
                    if (!$ticket->typeId) {
                        $this->addError('tickets', Craft::t('events', 'Ticket type must be set.'));

                        $ticket->addError('typeIds', Craft::t('events', 'Ticket type must be set.'));
                    } else if (!$ticket->validate()) {
                        $error = $ticket->getErrors()[0] ?? 'An error occurred';

                        $this->addError('tickets', Craft::t('events', $error));
                    }
                }
            },
        ];

        return $rules;
    }

    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $uid = $this->getType()->uid;

            return Craft::$app->getUser()->checkPermission('events-manageEventType:' . $uid);
        }

        return false;
    }

    public function getCpEditUrl(): ?string
    {
        $eventType = $this->getType();

        // The slug *might* not be set if this is a Draft, and they've deleted it for whatever reason
        $url = UrlHelper::cpUrl('events/events/' . $eventType->handle . '/' . $this->id . ($this->slug ? '-' . $this->slug : ''));

        if (Craft::$app->getIsMultiSite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }

    public function getFieldLayout(): ?FieldLayout
    {
        return $this->getType()->getEventFieldLayout();
    }

    public function getUriFormat(): ?string
    {
        $eventTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($eventTypeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('Event’s type (' . $this->getType()->id . ') is not enabled for site ' . $this->siteId);
        }

        return $eventTypeSiteSettings[$this->siteId]->uriFormat;
    }

    public function getSearchKeywords(string $attribute): string
    {
        if ($attribute === 'sku') {
            return implode(' ', ArrayHelper::getColumn($this->getTickets(), 'sku'));
        }

        return parent::getSearchKeywords($attribute);
    }

    public function getType(): EventType
    {
        if ($this->_eventType !== null) {
            return $this->_eventType;
        }

        if ($this->typeId === null) {
            throw new InvalidConfigException('Event is missing its event type ID');
        }

        $eventType = Events::$plugin->getEventTypes()->getEventTypeById($this->typeId);

        if (null === $eventType) {
            throw new InvalidConfigException('Invalid event type ID: ' . $this->typeId);
        }

        return $this->_eventType = $eventType;
    }

    public function getSnapshot(): array
    {
        $data = [
            'title' => $this->title,
        ];

        return array_merge($this->getAttributes(), $data);
    }

    public function getProduct(): static
    {
        return $this;
    }

    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status === self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return self::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    public function getTickets(): array
    {
        if (($this->_tickets === null) && $this->id) {
            $this->setTickets(Events::$plugin->getTickets()->getAllTicketsByEventId($this->id, $this->siteId));
        }

        return $this->_tickets ?? [];
    }

    public function setTickets(array $tickets): void
    {
        $count = 1;

        if (empty($tickets)) {
            $this->_tickets = [];
        }

        foreach ($tickets as $key => $ticket) {
            if (!$ticket instanceof Ticket) {
                $ticket = EventHelper::populateEventTicketModel($this, $ticket, $key);
            }

            $ticket->sortOrder = $count++;
            $ticket->setEvent($this);

            $this->_tickets[] = $ticket;
        }
    }

    public function setEagerLoadedElements(string $handle, array $elements): void
    {
        if ($handle == 'tickets') {
            $this->setTickets($elements);
        } else {
            parent::setEagerLoadedElements($handle, $elements);
        }
    }

    public function getIsAvailable(): bool
    {
        return (bool)$this->getAvailableTickets();
    }

    public function getAvailableTickets(): array
    {
        $tickets = $this->getTickets();

        foreach ($tickets as $key => $ticket) {
            if (!$ticket->getIsAvailable()) {
                unset($tickets[$key]);
            }
        }

        return $tickets;
    }

    public function getAvailableCapacity(): float|int
    {
        // If we've specifically not set a capacity on the event, treat it like unlimited
        if ($this->capacity === null) {
            return PHP_INT_MAX;
        }

        // Unlike a ticket's quantity, the event's capacity doesn't decrement, so in order to get 
        // the true capacity of the event, we need to factor in purchased tickets
        $purchasedTickets = PurchasedTicket::find()
            ->eventId($this->id)
            ->count();

        return $this->capacity - $purchasedTickets;
    }

    public function updateTitle(): void
    {
        $eventType = $this->getType();

        if (!$eventType->hasTitleField) {
            // Make sure that the locale has been loaded in case the title format has any Date/Time fields
            Craft::$app->getLocale();

            // Set Craft to the event's site's language, in case the title format has any static translations
            $language = Craft::$app->language;
            Craft::$app->language = $this->getSite()->language;

            $this->title = Craft::$app->getView()->renderObjectTemplate($eventType->titleFormat, $this);
            Craft::$app->language = $language;
        }
    }

    public function getIcsUrl(): string
    {
        return UrlHelper::actionUrl('events/ics', ['eventId' => $this->id]);
    }

    public function getIcsEvent(): ?CalendarEvent
    {
        if (!$this->startDate || !$this->endDate) {
            return null;
        }

        $eventType = $this->getType();

        $description = $this->title;
        $location = '';

        $descriptionFieldHandle = $eventType->icsDescriptionFieldHandle;
        $locationFieldHandle = $eventType->icsLocationFieldHandle;

        // See if we need to override the timezone for events
        $icsTimezone = $eventType->icsTimezone ?? '';

        if ($icsTimezone == '') {
            $startDate = $this->startDate;
            $endDate = $this->endDate;
        } else {
            $timezone = new DateTimeZone($icsTimezone);

            $startDate = $this->startDate->setTimeZone($timezone);
            $endDate = $this->endDate->setTimeZone($timezone);
        }

        $event = (new CalendarEvent())
            ->setStart($startDate)
            ->setEnd($endDate)
            ->setCreated($this->dateCreated)
            ->setLastModified($this->dateUpdated)
            ->setSummary($this->title)
            ->setAllDay($this->allDay)
            ->setStatus($this->status)
            ->setUrl($this->url)
            ->setUid($this->uid);

        if ($descriptionFieldHandle && isset($this->{$descriptionFieldHandle})) {
            $event->setDescription($this->{$descriptionFieldHandle});
        }

        if ($locationFieldHandle && isset($this->{$locationFieldHandle})) {
            $location = new Location();
            $location->setName($this->{$locationFieldHandle});

            $event->addLocation($location);
        }

        return $event;
    }

    public function beforeSave(bool $isNew): bool
    {
        // Make sure the field layout is set correctly
        $this->fieldLayoutId = $this->getType()->fieldLayoutId;

        $this->updateTitle();

        if ($this->enabled && !$this->postDate) {
            // Default the post date to the current date/time
            $this->postDate = new DateTime();
            // ...without the seconds
            $this->postDate->setTimestamp($this->postDate->getTimestamp() - ($this->postDate->getTimestamp() % 60));
        }

        return parent::beforeSave($isNew);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = EventRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid event id: ' . $this->id);
            }
        } else {
            $record = new EventRecord();
            $record->id = $this->id;
        }

        $record->allDay = $this->allDay;
        $record->capacity = $this->capacity;
        $record->startDate = $this->startDate;
        $record->endDate = $this->endDate;
        $record->postDate = $this->postDate;
        $record->expiryDate = $this->expiryDate;
        $record->typeId = $this->typeId;

        $record->save(false);

        $this->id = $record->id;

        // Only save tickets once (since they will propagate themselves the first time).
        if (!$this->propagating) {
            $keepTicketIds = [];
            $oldTicketIds = (new Query())
                ->select('id')
                ->from('{{%events_tickets}}')
                ->where(['eventId' => $this->id])
                ->column();

            foreach ($this->getTickets() as $ticket) {
                if ($isNew) {
                    $ticket->eventId = $this->id;
                    $ticket->siteId = $this->siteId;
                }

                $keepTicketIds[] = $ticket->id;

                Craft::$app->getElements()->saveElement($ticket, false);
            }

            foreach (array_diff($oldTicketIds, $keepTicketIds) as $deleteId) {
                Craft::$app->getElements()->deleteElementById($deleteId);
            }
        }

        parent::afterSave($isNew);
    }

    public function beforeRestore(): bool
    {
        $tickets = Ticket::find()->trashed(null)->eventId($this->id)->status(null)->all();
        Craft::$app->getElements()->restoreElements($tickets);
        $this->setTickets($tickets);

        return parent::beforeRestore();
    }

    public function beforeValidate(): bool
    {
        // We need to generate all ticket sku formats before validating the product,
        // since the event validates the uniqueness of all tickets in memory.
        $type = $this->getType();

        foreach ($this->getTickets() as $ticket) {
            if (!$ticket->sku) {
                $ticket->sku = TicketHelper::generateTicketSKU();
            }
        }

        return parent::beforeValidate();
    }

    public function afterDelete(): void
    {
        $tickets = Ticket::find()
            ->eventId($this->id)
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($tickets as $ticket) {
            $ticket->deletedWithEvent = true;
            $elementsService->deleteElement($ticket);
        }

        parent::afterDelete();
    }

    public function afterRestore(): void
    {
        // Also restore any tickets for this element
        $tickets = Ticket::find()
            ->status(null)
            ->siteId($this->siteId)
            ->eventId($this->id)
            ->trashed()
            ->andWhere(['events_tickets.deletedWithEvent' => true])
            ->all();

        Craft::$app->getElements()->restoreElements($tickets);
        $this->setTickets($tickets);

        parent::afterRestore();
    }


    // Protected methods
    // =========================================================================

    protected function route(): array|string|null
    {
        // Make sure the event type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $eventTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($eventTypeSiteSettings[$siteId]) || !$eventTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $eventTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'event' => $this,
                    'product' => $this,
                ],
            ],
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        $eventType = $this->getType();

        return match ($attribute) {
            'type' => $eventType ? Craft::t('events', $eventType->name) : '',
            default => parent::tableAttributeHtml($attribute),
        };
    }

}
