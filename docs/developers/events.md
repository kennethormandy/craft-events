# Events
Events can be used to extend the functionality of Events.

## Ticket PDF related events

### The `beforeRenderPdf` event
Event handlers can override Ticket’s PDF generation by setting the `pdf` property on the event to a custom-rendered PDF.
Plugins can get notified before the PDF or a ticket is being rendered.

```php
use verbb\events\events\PdfEvent;
use verbb\events\services\Pdf;
use yii\base\Event;

Event::on(Pdf::class, Pdf::EVENT_BEFORE_RENDER_PDF, function(PdfEvent $event) {
     // Roll out our own custom PDF
});
```

### The `afterRenderPdf` event
Plugins can get notified after the PDF or a ticket has been rendered.

```php
use verbb\events\events\PdfEvent;
use verbb\events\services\Pdf;
use yii\base\Event;

Event::on(Pdf::class, Pdf::EVENT_AFTER_RENDER_PDF, function(PdfEvent $event) {
     // Add a watermark to the PDF or forward it to the accounting dpt.
});
```

### The `modifyRenderOptions` event
Plugins can get modify the DomPDF render options

```php
use verbb\events\events\PdfRenderOptionsEvent;
use verbb\events\services\Pdf;
use yii\base\Event;

Event::on(Pdf::class, Pdf::EVENT_MODIFY_RENDER_OPTIONS, function(PdfRenderOptionsEvent $event) {

});
```


## Event related events

### The `beforeSaveEvent` event

Plugins can get notified before an event is saved. Event handlers can prevent the event from getting saved by setting `$event->isValid` to false.

```php
use craft\events\ModelEvent;
use verbb\events\elements\Event as EventElement;
use yii\base\Event;

Event::on(EventElement::class, EventElement::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $eventElement = $event->sender;
    $event->isValid = false;
});
```

### The `afterSaveEvent` event

Plugins can get notified after an event has been saved

```php
use craft\events\ModelEvent;
use verbb\events\elements\Event as EventElement;
use yii\base\Event;

Event::on(EventElement::class, EventElement::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $eventElement = $event->sender;
});
```

### The `beforeDeleteEvent` event
The event that is triggered before an event is deleted.

The `isValid` event property can be set to `false` to prevent the deletion from proceeding.

```php
use verbb\events\elements\Event as EventElement;
use yii\base\Event;

Event::on(EventElement::class, EventElement::EVENT_BEFORE_DELETE, function(Event $event) {
    $eventElement = $event->sender;
    $event->isValid = false;
});
```

### The `afterDeleteEvent` event
The event that is triggered after an event is deleted.

```php
use verbb\events\elements\Event as EventElement;
use yii\base\Event;

Event::on(EventElement::class, EventElement::EVENT_AFTER_DELETE, function(Event $event) {
    $eventElement = $event->sender;
});
```


## Event Type related events

### The `beforeSaveEventType` event

Plugins can get notified before an event type is being saved.

```php
use verbb\events\events\EventTypeEvent;
use verbb\events\services\EventTypes;
use yii\base\Event;

Event::on(EventTypes::class, EventTypes::EVENT_BEFORE_SAVE_EVENTTYPE, function(EventTypeEvent $event) {
     // Maybe create an audit trail of this action.
});
```

### The `afterSaveEventType` event

Plugins can get notified after an event type has been saved.

```php
use verbb\events\events\EventTypeEvent;
use verbb\events\services\EventTypes;
use yii\base\Event;

Event::on(EventTypes::class, EventTypes::EVENT_AFTER_SAVE_EVENTTYPE, function(EventTypeEvent $event) {
     // Maybe prepare some third party system for a new event type
});
```



## Ticket related events

### The `beforeSaveTicket` event

Plugins can get notified before a ticket is saved. Event handlers can prevent the ticket from getting saved by setting `$event->isValid` to false.

```php
use craft\events\ModelEvent;
use verbb\events\elements\Ticket;
use yii\base\Event;

Event::on(Ticket::class, Ticket::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $ticket = $event->sender;
    $event->isValid = false;
});
```

### The `afterSaveTicket` event

Plugins can get notified after a ticket has been saved

```php
use craft\events\ModelEvent;
use verbb\events\elements\Ticket;
use yii\base\Event;

Event::on(Ticket::class, Ticket::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $ticket = $event->sender;
});
```

### The `beforeDeleteTicket` event
The event that is triggered before a ticket is deleted.

The `isValid` event property can be set to `false` to prevent the deletion from proceeding.

```php
use verbb\events\elements\Ticket;
use yii\base\Event;

Event::on(Ticket::class, Ticket::EVENT_BEFORE_DELETE, function(Event $event) {
    $ticket = $event->sender;
    $event->isValid = false;
});
```

### The `afterDeleteTicket` event
The event that is triggered after a ticket is deleted.

```php
use verbb\events\elements\Ticket;
use yii\base\Event;

Event::on(Ticket::class, Ticket::EVENT_AFTER_DELETE, function(Event $event) {
    $ticket = $event->sender;
});
```

### The `beforeCaptureTicketSnapshot` event

Plugins can get notified before we capture a ticket’s field data, and customize which fields are included.

```php
use verbb\events\elements\Ticket;
use verbb\events\events\CustomizeTicketSnapshotFieldsEvent;

Event::on(Ticket::class, Variant::EVENT_BEFORE_CAPTURE_TICKET_SNAPSHOT, function(CustomizeTicketSnapshotFieldsEvent $event) {
    $ticket = $event->ticket;
    $fields = $event->fields;
    // Modify fields, or set to `null` to capture all.
});
```

### The `afterCaptureTicketSnapshot` event

Plugins can get notified after we capture a ticket’s field data, and customize, extend, or redact the data to be persisted.

```php
use verbb\events\elements\Ticket;
use verbb\events\events\CustomizeTicketSnapshotDataEvent;

Event::on(Ticket::class, Ticket::EVENT_AFTER_CAPTURE_TICKET_SNAPSHOT, function(CustomizeTicketSnapshotFieldsEvent $event) {
    $ticket = $event->ticket;
    $data = $event->fieldData;
    // Modify or redact captured `$data`...
});
```

### The `beforeCaptureEventSnapshot` event

Plugins can get notified before we capture an event’s field data, and customize which fields are included.

```php
use verbb\events\elements\Event as EventElement;
use verbb\events\events\CustomizeEventSnapshotFieldsEvent;

Event::on(EventElement::class, EventElement::EVENT_BEFORE_CAPTURE_EVENT_SNAPSHOT, function(CustomizeEventSnapshotFieldsEvent $event) {
    $eventElement = $event->event;
    $fields = $event->fields;
    // Modify fields, or set to `null` to capture all.
});
```

### The `afterCaptureEventSnapshot` event

Plugins can get notified after we capture an event’s field data, and customize, extend, or redact the data to be persisted.

```php
use verbb\events\elements\Event as EventElement;
use verbb\events\events\CustomizeEventSnapshotDataEvent;

Event::on(EventElement::class, EventElement::EVENT_AFTER_CAPTURE_EVENT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $event) {
    $eventElement = $event->event;
    $data = $event->fieldData;
    // Modify or redact captured `$data`...
});
```


## Purchased Ticket related events

### The `beforeSavePurchasedTicket` event

Plugins can get notified before a purchased ticket is saved. Event handlers can prevent the purchased ticket from getting saved by setting `$event->isValid` to false.

```php
use craft\events\ModelEvent;
use verbb\events\elements\PurchasedTicket;
use yii\base\Event;

Event::on(PurchasedTicket::class, PurchasedTicket::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $purchasedTicket = $event->sender;
    $event->isValid = false;
});
```

### The `afterSavePurchasedTicket` event

Plugins can get notified after a purchased ticket has been saved

```php
use craft\events\ModelEvent;
use verbb\events\elements\PurchasedTicket;
use yii\base\Event;

Event::on(PurchasedTicket::class, PurchasedTicket::EVENT_AFTER_SAVE, function(ModelEvent $event) {
    $isNew = $event->isNew;
    $purchasedTicket = $event->sender;
});
```

### The `beforeDeletePurchasedTicket` event
The event that is triggered before a purchased ticket is deleted.

The `isValid` event property can be set to `false` to prevent the deletion from proceeding.

```php
use verbb\events\elements\PurchasedTicket;
use yii\base\Event;

Event::on(PurchasedTicket::class, PurchasedTicket::EVENT_BEFORE_DELETE, function(Event $event) {
    $purchasedTicket = $event->sender;
    $event->isValid = false;
});
```

### The `afterDeletePurchasedTicket` event
The event that is triggered after a purchased ticket is deleted.

```php
use verbb\events\elements\PurchasedTicket;
use yii\base\Event;

Event::on(PurchasedTicket::class, PurchasedTicket::EVENT_AFTER_DELETE, function(Event $event) {
    $purchasedTicket = $event->sender;
});
```
