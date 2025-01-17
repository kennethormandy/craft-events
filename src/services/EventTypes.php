<?php
namespace verbb\events\services;

use verbb\events\Events;
use verbb\events\elements\Event;
use verbb\events\elements\Session;
use verbb\events\elements\TicketType;
use verbb\events\errors\EventTypeNotFoundException;
use verbb\events\events\EventTypeEvent;
use verbb\events\models\EventType;
use verbb\events\models\EventTypeSite;
use verbb\events\records\EventType as EventTypeRecord;
use verbb\events\records\EventTypeSite as EventTypeSiteRecord;

use Craft;
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\db\Table;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\DeleteSiteEvent;
use craft\events\SiteEvent;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;

use craft\queue\jobs\ResaveElements;
use yii\base\Component;

use Throwable;

class EventTypes extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_BEFORE_SAVE_EVENTTYPE = 'beforeSaveEventType';
    public const EVENT_AFTER_SAVE_EVENTTYPE = 'afterSaveEventType';
    public const CONFIG_EVENTTYPES_KEY = 'events.eventTypes';


    // Properties
    // =========================================================================

    private ?MemoizableArray $_eventTypes = null;


    // Public Methods
    // =========================================================================

    public function getAllEventTypes(): array
    {
        return $this->_eventTypes()->all();
    }

    public function getAllEventTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getAllEventTypes(), 'id', false);
    }

    public function getEventTypeByHandle(string $handle): ?EventType
    {
        return $this->_eventTypes()->firstWhere('handle', $handle, true);
    }

    public function getEventTypeById(int $id): ?EventType
    {
        return $this->_eventTypes()->firstWhere('id', $id);
    }

    public function getEventTypeByUid(string $uid): ?EventType
    {
        return $this->_eventTypes()->firstWhere('uid', $uid, true);
    }

    public function getEditableEventTypes(): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        
        return ArrayHelper::where($this->getAllEventTypes(), function(EventType $eventType) use ($user) {
            return $user->can("events-editEvents:{$eventType->uid}");
        }, true, true, false);
    }

    public function getEditableEventTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getEditableEventTypes(), 'id', false);
    }

    public function getViewableEventTypes(): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        
        return ArrayHelper::where($this->getAllEventTypes(), function(EventType $eventType) use ($user) {
            return $user->can("events-viewEvents:{$eventType->uid}");
        }, true, true, false);
    }

    public function getViewableEventTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getViewableEventTypes(), 'id', false);
    }

    public function getCreatableEventTypes(): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        
        return ArrayHelper::where($this->getAllEventTypes(), function(EventType $eventType) use ($user) {
            return $user->can("events-createEvents:{$eventType->uid}");
        }, true, true, false);
    }

    public function getCreatableEventTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getCreatableEventTypes(), 'id', false);
    }

    public function getEventTypeSites(int $eventTypeId): array
    {
        $results = EventTypeSiteRecord::find()
            ->where(['eventTypeId' => $eventTypeId])
            ->all();

        $siteSettings = [];

        foreach ($results as $result) {
            $siteSettings[] = new EventTypeSite($result->toArray([
                'id',
                'eventTypeId',
                'siteId',
                'uriFormat',
                'hasUrls',
                'template',
            ]));
        }

        return $siteSettings;
    }

    public function saveEventType(EventType $eventType, bool $runValidation = true): bool
    {
        $isNewEventType = !$eventType->id;

        // Fire a 'beforeSaveEventType' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_EVENTTYPE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_EVENTTYPE, new EventTypeEvent([
                'eventType' => $eventType,
                'isNew' => $isNewEventType,
            ]));
        }

        if ($runValidation && !$eventType->validate()) {
            Events::info('Event type not saved due to validation error.');

            return false;
        }

        if ($isNewEventType) {
            $eventType->uid = StringHelper::UUID();
        } else {
            $existingEventTypeRecord = EventTypeRecord::find()
                ->where(['id' => $eventType->id])
                ->one();

            if (!$existingEventTypeRecord) {
                throw new EventTypeNotFoundException("No event type exists with the ID '{$eventType->id}'");
            }

            $eventType->uid = $existingEventTypeRecord->uid;
        }

        $configPath = self::CONFIG_EVENTTYPES_KEY . '.' . $eventType->uid;
        Craft::$app->getProjectConfig()->set($configPath, $eventType->getConfig());

        if ($isNewEventType) {
            $eventType->id = Db::idByUid('{{%events_event_types}}', $eventType->uid);
        }

        return true;
    }

    public function handleChangedEventType(ConfigEvent $event): void
    {
        $eventTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;
        $shouldResaveEvents = false;
        $shouldResaveEventSessions = false;

        // Make sure fields and sites are processed
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $siteData = $data['siteSettings'];

            // Basic data
            $eventTypeRecord = $this->_getEventTypeRecord($eventTypeUid);
            $isNewEventType = $eventTypeRecord->getIsNewRecord();
            $fieldsService = Craft::$app->getFields();

            $eventTypeRecord->uid = $eventTypeUid;
            $eventTypeRecord->name = $data['name'];
            $eventTypeRecord->handle = $data['handle'];
            $eventTypeRecord->enableVersioning = $data['enableVersioning'];
            $eventTypeRecord->icsTimezone = $data['icsTimezone'] ?? null;
            $eventTypeRecord->icsDescriptionFieldHandle = $data['icsDescriptionFieldHandle'] ?? null;
            $eventTypeRecord->icsLocationFieldHandle = $data['icsLocationFieldHandle'] ?? null;

            // Changing titles for things will mean we need to resave elements
            $sessionTitleFormat = $data['sessionTitleFormat'] ?? '{dateSummary}';
            $ticketTitleFormat = $data['ticketTitleFormat'] ?? '{type.title} - {session.title}';
            $ticketSkuFormat = $data['ticketSkuFormat'] ?? '';
            $purchasedTicketTitleFormat = $data['purchasedTicketTitleFormat'] ?? '{event.title} - {ticket.title}';

            if ($eventTypeRecord->sessionTitleFormat != $sessionTitleFormat) {
                $shouldResaveEventSessions = true;
            }

            $eventTypeRecord->sessionTitleFormat = $sessionTitleFormat;
            $eventTypeRecord->ticketTitleFormat = $ticketTitleFormat;
            $eventTypeRecord->ticketSkuFormat = $ticketSkuFormat;
            $eventTypeRecord->purchasedTicketTitleFormat = $purchasedTicketTitleFormat;

            if (!empty($data['eventFieldLayouts']) && !empty($config = reset($data['eventFieldLayouts']))) {
                // Save the main field layout
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $eventTypeRecord->fieldLayoutId;
                $layout->type = Event::class;
                $layout->uid = key($data['eventFieldLayouts']);

                $fieldsService->saveLayout($layout, false);

                $eventTypeRecord->fieldLayoutId = $layout->id;
            } else if ($eventTypeRecord->fieldLayoutId) {
                // Delete the main field layout
                $fieldsService->deleteLayoutById($eventTypeRecord->fieldLayoutId);
                $eventTypeRecord->fieldLayoutId = null;
            }

            if (!empty($data['sessionFieldLayouts']) && !empty($config = reset($data['sessionFieldLayouts']))) {
                // Save the session field layout
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $eventTypeRecord->sessionFieldLayoutId;
                $layout->type = Session::class;
                $layout->uid = key($data['sessionFieldLayouts']);

                $fieldsService->saveLayout($layout, false);

                $eventTypeRecord->sessionFieldLayoutId = $layout->id;
            } elseif ($eventTypeRecord->sessionFieldLayoutId) {
                // Delete the session field layout
                $fieldsService->deleteLayoutById($eventTypeRecord->sessionFieldLayoutId);
                $eventTypeRecord->sessionFieldLayoutId = null;
            }

            if (!empty($data['ticketFieldLayouts']) && !empty($config = reset($data['ticketFieldLayouts']))) {
                // Save the ticket field layout
                $layout = FieldLayout::createFromConfig($config);
                $layout->id = $eventTypeRecord->ticketTypeFieldLayoutId;
                $layout->type = TicketType::class;
                $layout->uid = key($data['ticketFieldLayouts']);

                $fieldsService->saveLayout($layout, false);

                $eventTypeRecord->ticketTypeFieldLayoutId = $layout->id;
            } elseif ($eventTypeRecord->ticketTypeFieldLayoutId) {
                // Delete the ticket field layout
                $fieldsService->deleteLayoutById($eventTypeRecord->ticketTypeFieldLayoutId);
                $eventTypeRecord->ticketTypeFieldLayoutId = null;
            }

            $eventTypeRecord->save(false);

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];
            $allOldSiteSettingsRecords = [];

            if (!$isNewEventType) {
                // Get the old event type site settings
                $allOldSiteSettingsRecords = EventTypeSiteRecord::find()
                    ->where(['eventTypeId' => $eventTypeRecord->id])
                    ->indexBy('siteId')
                    ->all();
            }

            $siteIdMap = Db::idsByUids('{{%sites}}', array_keys($siteData));

            /** @var EventTypeSiteRecord $siteSettings */
            foreach ($siteData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid];

                // Was this already selected?
                if (!$isNewEventType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new EventTypeSiteRecord();
                    $siteSettingsRecord->eventTypeId = $eventTypeRecord->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                $siteSettingsRecord->enabledByDefault = (bool)($siteSettings['enabledByDefault'] ?? true);

                if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                    $siteSettingsRecord->template = $siteSettings['template'];
                } else {
                    $siteSettingsRecord->uriFormat = null;
                    $siteSettingsRecord->template = null;
                }

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings['hasUrls']) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings['hasUrls'] && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);
            }

            if (!$isNewEventType) {
                // Drop any site settings that are no longer being used, as well as the associated event/element
                // site rows
                $affectedSiteUids = array_keys($siteData);

                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    $siteUid = array_search($siteId, $siteIdMap, false);
                    if (!in_array($siteUid, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                        $shouldResaveEvents = true;
                    }
                }
            }

            // Finally, deal with the existing events...
            // -----------------------------------------------------------------

            if (!$isNewEventType) {
                // Get all the event IDs in this group
                $eventIds = Event::find()
                    ->typeId($eventTypeRecord->id)
                    ->status(null)
                    ->limit(null)
                    ->ids();

                // Are there any sites left?
                if (!empty($siteData)) {
                    // Drop the old event URIs for any site settings that don't have URLs
                    if (!empty($sitesNowWithoutUrls)) {
                        Db::update('{{%elements_sites}}', ['uri' => null], [
                            'elementId' => $eventIds,
                            'siteId' => $sitesNowWithoutUrls,
                        ]);
                    } else if (!empty($sitesWithNewUriFormats)) {
                        foreach ($eventIds as $eventId) {
                            App::maxPowerCaptain();

                            // Loop through each of the changed sites and update all the events’ slugs and URIs
                            foreach ($sitesWithNewUriFormats as $siteId) {
                                $event = Event::find()
                                    ->id($eventId)
                                    ->siteId($siteId)
                                    ->status(null)
                                    ->one();

                                if ($event) {
                                    Craft::$app->getElements()->updateElementSlugAndUri($event, false, false);
                                }
                            }
                        }
                    }
                }
            }

            $transaction->commit();

            if ($shouldResaveEvents) {
                Craft::$app->getQueue()->push(new ResaveElements([
                    'elementType' => Event::class,
                    'criteria' => [
                        'siteId' => '*',
                        'status' => null,
                        'typeId' => $eventTypeRecord->id,
                    ],
                ]));
            }

            if ($shouldResaveEventSessions) {
                Craft::$app->getQueue()->push(new ResaveElements([
                    'elementType' => Session::class,
                    'criteria' => [
                        'siteId' => '*',
                        'status' => null,
                        'eventId' => Event::find()->typeId($eventTypeRecord->id)->status(null)->ids(),
                    ],
                ]));
            }
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_eventTypes = null;

        // Fire an 'afterSaveEventType' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EVENTTYPE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EVENTTYPE, new EventTypeEvent([
                'eventType' => $this->getEventTypeById($eventTypeRecord->id),
            ]));
        }
    }

    public function deleteEventTypeById(int $id): bool
    {
        $eventType = $this->getEventTypeById($id);
        Craft::$app->getProjectConfig()->remove(self::CONFIG_EVENTTYPES_KEY . '.' . $eventType->uid);
        return true;
    }

    public function handleDeletedEventType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $eventTypeRecord = $this->_getEventTypeRecord($uid);

        if (!$eventTypeRecord->id) {
            return;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $events = Event::find()
                ->typeId($eventTypeRecord->id)
                ->status(null)
                ->limit(null)
                ->all();

            foreach ($events as $eventElement) {
                Craft::$app->getElements()->deleteElement($eventElement);
            }

            if ($fieldLayoutId = $eventTypeRecord->fieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);
            }

            if ($sessionFieldLayoutId = $eventTypeRecord->sessionFieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($sessionFieldLayoutId);
            }

            if ($ticketTypeFieldLayoutId = $eventTypeRecord->ticketTypeFieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($ticketTypeFieldLayoutId);
            }

            $eventTypeRecord->delete();
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Clear caches
        $this->_eventTypes = null;
    }

    public function pruneDeletedSite(DeleteSiteEvent $event): void
    {
        $siteUid = $event->site->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $eventTypes = $projectConfig->get(self::CONFIG_EVENTTYPES_KEY);

        // Loop through the event types and prune the UID from field layouts.
        if (is_array($eventTypes)) {
            foreach ($eventTypes as $eventTypeUid => $eventType) {
                $projectConfig->remove(self::CONFIG_EVENTTYPES_KEY . '.' . $eventTypeUid . '.siteSettings.' . $siteUid);
            }
        }
    }

    public function isEventTypeTemplateValid(EventType $eventType, int $siteId): bool
    {
        $eventTypeSiteSettings = $eventType->getSiteSettings();

        if (isset($eventTypeSiteSettings[$siteId]) && $eventTypeSiteSettings[$siteId]->hasUrls && $eventTypeSiteSettings[$siteId]->template) {
            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = Craft::$app->getView()->doesTemplateExist($eventTypeSiteSettings[$siteId]->template);

            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        if ($event->isNew && isset($event->oldPrimarySiteId)) {
            $oldPrimarySiteUid = Db::uidById(Table::SITES, $event->oldPrimarySiteId);
            $projectConfig = Craft::$app->getProjectConfig();
            $existingEventTypeSettings = $projectConfig->get(self::CONFIG_EVENTTYPES_KEY);

            if (!$projectConfig->getIsApplyingExternalChanges() && is_array($existingEventTypeSettings)) {
                foreach ($existingEventTypeSettings as $eventTypeUid => $settings) {
                    $primarySiteSettings = $settings['siteSettings'][$oldPrimarySiteUid];
                    $configPath = self::CONFIG_EVENTTYPES_KEY . '.' . $eventTypeUid . '.siteSettings.' . $event->site->uid;
                    $projectConfig->set($configPath, $primarySiteSettings);
                }
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _eventTypes(): MemoizableArray
    {
        if (!isset($this->_eventTypes)) {
            $eventTypes = [];

            foreach ($this->_createEventTypeQuery()->all() as $result) {
                $eventTypes[] = new EventType($result);
            }

            $this->_eventTypes = new MemoizableArray($eventTypes);
        }

        return $this->_eventTypes;
    }

    private function _createEventTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'eventTypes.id',
                'eventTypes.fieldLayoutId',
                'eventTypes.sessionFieldLayoutId',
                'eventTypes.ticketTypeFieldLayoutId',
                'eventTypes.name',
                'eventTypes.handle',
                'eventTypes.enableVersioning',
                'eventTypes.sessionTitleFormat',
                'eventTypes.ticketTitleFormat',
                'eventTypes.ticketSkuFormat',
                'eventTypes.purchasedTicketTitleFormat',
                'eventTypes.icsTimezone',
                'eventTypes.icsDescriptionFieldHandle',
                'eventTypes.icsLocationFieldHandle',
                'eventTypes.uid',
            ])
            ->from(['{{%events_event_types}} eventTypes']);
    }

    private function _getEventTypeRecord(string $uid): EventTypeRecord
    {
        return EventTypeRecord::findOne(['uid' => $uid]) ?? new EventTypeRecord();
    }
}
