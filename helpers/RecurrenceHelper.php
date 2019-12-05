<?php


namespace humhub\modules\calendar\helpers;


use DateTime;
use DateTimeZone;
use humhub\modules\calendar\interfaces\CalendarEventIF;
use humhub\modules\calendar\interfaces\recurrence\RecurrentCalendarEventIF;
use humhub\modules\calendar\interfaces\VCalendar;
use Sabre\VObject\Component\VEvent;

class RecurrenceHelper
{
    const ICAL_TIME_FORMAT = 'Ymd\THis';

    public static function getRecurrenceIds(CalendarEventIF $event, DateTime $start, DateTime $end)
    {
        $result = [];
        $recurrences = static::calculateRecurrenceInstances($event, $start, $end);
        foreach ($recurrences as $vEvent) {
            $result[] = static::getRecurrenceIdFromVEvent($vEvent, $event->getTimezone());
        }

        return $result;
    }

    public static function getRecurrenceIdFromVEvent(VEvent $vEvent, $eventTZ)
    {
        if(is_string($eventTZ)) {
            $eventTZ = new DateTimeZone($eventTZ);
        }

        $recurrence_id = $vEvent->{'RECURRENCE-ID'}->getValue();
        // We only need to translate from UTC to event timezone for non all day events
        $tz = (strrpos($recurrence_id, 'T') === false) ? null : $eventTZ;
        return  static::cleanRecurrentId($vEvent->{'RECURRENCE-ID'}->getValue(), $tz);
    }

    public static function calculateRecurrenceInstances(CalendarEventIF $event, DateTime $start, DateTime $end)
    {
        // Note: VObject supports the EXDATE property for exclusions, but not yet the RDATE and EXRULE properties
        // Note: VCalendar expand will translate all dates with time to UTC

        $tz = (is_string($event->getTimezone())) ? new DateTimeZone($event->getTimezone()) : $event->getTimezone();

        $vCalendar = (new VCalendar())->add($event);
        $expandedVCalendar = $vCalendar->getInstance()->expand($start, $end, $tz);
        return $expandedVCalendar->select('VEVENT');
    }

    public static function cleanRecurrentId($recurrentId, $targetTZ = null)
    {
        $date = ($recurrentId instanceof \DateTimeInterface) ? $recurrentId : new DateTime($recurrentId, new DateTimeZone('UTC'));

        if($targetTZ) {
            $date->setTimezone(new DateTimeZone($targetTZ));
        }

        return $date->format(static::ICAL_TIME_FORMAT);
    }

    public static function isRecurrent(CalendarEventIF $evt)
    {
        if(!$evt instanceof RecurrentCalendarEventIF) {
            return false;
        }

        return !empty($evt->getRrule());
    }

    public static function isRecurrentInstance(CalendarEventIF $evt)
    {
        if(!$evt instanceof RecurrentCalendarEventIF) {
            return false;
        }

        return static::isRecurrent($evt) && $evt->getRecurrenceId() && $evt->getRecurrenceRootId();
    }

    public static function isRecurrentRoot(CalendarEventIF $evt)
    {
        if(!$evt instanceof RecurrentCalendarEventIF) {
            return false;
        }

        return static::isRecurrent($evt) && !$evt->getRecurrenceRootId();
    }

}