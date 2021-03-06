<?php
/**
 * This file is part of the CalendArt package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Wisembly
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

namespace CalendArt\Adapter\Google\Api;

use InvalidArgumentException;

use Doctrine\Common\Collections\ArrayCollection;

use CalendArt\Adapter\EventApiInterface;
use CalendArt\AbstractEvent as CalendArtAbstractEvent;

use CalendArt\Adapter\Google\Model\BasicEvent;
use CalendArt\Adapter\Google\Model\AbstractEvent;
use CalendArt\Adapter\Google\Model\Calendar;
use CalendArt\Adapter\Google\Exception\CriterionNotFoundException;
use CalendArt\Adapter\Google\Criterion\AbstractCriterion;
use CalendArt\Adapter\Google\Criterion\Field;
use CalendArt\Adapter\Google\Criterion\Collection;
use CalendArt\Adapter\Google\GoogleAdapter;

/**
 * Google Adapter for the Calendars
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class EventApi implements EventApiInterface
{
    /** @var Calendar */
    private $calendar;

    /** @var Field[] */
    private $fields;

    /** @var GoogleAdapter */
    private $adapter;

    public function __construct(GoogleAdapter $adapter, Calendar $calendar)
    {
        $this->adapter  = $adapter;
        $this->calendar = $calendar;

        $this->fields = [new Field('id'),
                         new Field('end'),
                         new Field('etag'),
                         new Field('start'),
                         new Field('status'),
                         new Field('created'),
                         new Field('updated'),
                         new Field('summary'),
                         new Field('location'),
                         new Field('organizer'),
                         new Field('description'),
                         new Field('creator', [new Field('email'),
                                               new Field('displayName')]),

                         new Field('attendees', [new Field('email'),
                                                 new Field('resource'),
                                                 new Field('organizer'),
                                                 new Field('displayName'),
                                                 new Field('responseStatus')])];
    }

    /** {@inheritDoc} */
    public function getList(AbstractCriterion $criterion = null)
    {
        $nextPageToken = null;
        $query         = new Collection([]);
        $list          = new ArrayCollection;
        $calendars     = new ArrayCollection;

        $calendars[$this->calendar->getId()] = $this->calendar;

        $fields = [new Field('nextSyncToken'),
                   new Field('nextPageToken'),
                   new Field('items', $this->fields)];

        $query->addCriterion(new Collection([new Field(null, $fields)], 'fields'));

        if (null !== $criterion) {
            $query = $query->merge($criterion);
        }

        try {
            $showDeleted = (bool) $query->getCriterion('showDeleted');
        } catch (CriterionNotFoundException $e) {
            $showDeleted = false;
        }

        $query = $query->build();

        do {
            $current = $query;

            if (null !== $nextPageToken) {
                $current['pageToken'] = $nextPageToken;
            }

            $result = $this->adapter->sendRequest(
                'get',
                sprintf('/calendar/v3/calendars/%s/events', $this->calendar->getId()),
                ['query' => $current]
            );

            foreach ($result['items'] as $item) {
                // ignore the short cancelled recurring events
                if (!$showDeleted && isset($item['status']) && AbstractEvent::STATUS_CANCELLED === $item['status']) {
                    continue;
                }

                $calendar = $this->calendar;

                // match the _real_ calendar for this event
                if (isset($item['organizer']) && (!isset($item['organizer']['self']) || false === $item['organizer']['self'])) {
                    try {
                        $data = $item['organizer'];

                        // the email is usually an identifier for the calendars
                        if (!isset($data['id'])) {
                            if (!isset($data['email'])) {
                                throw new InvalidArgumentException;
                            }

                            $data['id'] = $data['email'];
                        }

                        if (!isset($calendars[$data['id']])) {
                            $data += ['summary' => !isset($data['displayName'])
                                        ? !isset($data['email'])
                                            ? null
                                            : $data['email']
                                        : $data['displayName'],

                                      'timeZone' => null];

                            $calendars[$data['id']] = Calendar::hydrate($data);
                        }

                        $calendar = $calendars[$data['id']];
                    } catch (InvalidArgumentException $e) {
                        $calendar = $this->calendar;
                    }
                }

                $list[$item['id']] = BasicEvent::hydrate($calendar, $item);

                if ($calendar !== $this->calendar) {
                    $this->calendar->getEvents()->add($list[$item['id']]);
                }
            }

            $nextPageToken = isset($result['nextPageToken']) ? $result['nextPageToken'] : null;
        } while (null !== $nextPageToken);

        $this->calendar->setSyncToken($result['nextSyncToken']);

        return $list;
    }

    /** @return Calendar */
    public function getCalendar()
    {
        return $this->calendar;
    }

    /** {@inheritDoc} */
    public function get($identifier, AbstractCriterion $criterion = null)
    {
        $query = new Collection($this->fields, 'fields');

        if (null !== $criterion) {
            $query = $query->merge($criterion);
        }

        $result = $this->adapter->sendRequest(
            'get',
            sprintf('/calendar/v3/calendars/%s/events/%s', $this->calendar->getId(), $identifier),
            ['query' => $query->build()]
        );

        $calendar = $this->calendar;

        // match the _real_ calendar for this event
        if (isset($result['organizer']) && (!isset($result['organizer']['self']) || false === $result['organizer']['self'])) {
            try {
                $data = $result['organizer'];

                // the email is usually an identifier for the calendars
                if (!isset($data['id'])) {
                    if (!isset($data['email'])) {
                        throw new InvalidArgumentException;
                    }

                    $data['id'] = $data['email'];
                }

                $data += ['summary' => !isset($data['displayName'])
                    ? !isset($data['email'])
                        ? null
                        : $data['email']
                    : $data['displayName'],

                    'timeZone' => null];

                $calendar = Calendar::hydrate($data);
            } catch (InvalidArgumentException $e) {
                $calendar = $this->calendar;
            }
        }

        return BasicEvent::hydrate($calendar, $result);
    }

    /**
     * {@inheritDoc}
     *
     * $options['sendNotifications'] boolean Whether to send notifications about the event update.
     * Optional. The default is false.
     */
    public function persist(CalendArtAbstractEvent $event, array $options = [])
    {
        if (!$event instanceof AbstractEvent) {
            throw new InvalidArgumentException('Wrong event provided, expected a google event');
        }

        if (null !== $event->getId()) {
            $url = sprintf('/calendar/v3/calendars/%s/events/%s', $event->getCalendar()->getId(), $event->getId());
            $method = 'patch';
        } else {
            $url = sprintf('/calendar/v3/calendars/%s/events', $event->getCalendar()->getId());
            $method = 'post';
        }

        $query = [];

        // manage the options now
        // should we send notifications to the attendees ?
        if (isset($options['sendNotifications'])) {
            $query['sendNotifications'] = $options['sendNotifications'] ? 'true' : 'false';
        }

        $result = $this->adapter->sendRequest($method, $url, ['query' => $query], json_encode($event->export()));
        return BasicEvent::hydrate($this->calendar, $result);
    }

    /** @return GoogleAdapter */
    public function getAdapter()
    {
        return $this->adapter;
    }
}
