<?php
namespace verbb\events\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    public string $pluginName = 'Events';
    public string $ticketPdfPath = 'shop/_pdf/tickets';
    public string $ticketPdfFilenameFormat = 'Tickets-{number}';
    public bool $checkinLogin = false;
    public string $checkinTemplate = '';
    public bool $ticketsShippable = false;
    public array $attachPdfToEmails = [];

    public bool $pdfAllowRemoteImages = false;
    public string $pdfPaperSize = 'letter';
    public string $pdfPaperOrientation = 'portrait';

}
