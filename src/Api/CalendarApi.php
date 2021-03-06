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

use CalendArt\Adapter\Google\Criterion\AbstractCriterion;
use CalendArt\Adapter\Google\GoogleAdapter;
use CalendArt\Adapter\Google\Model\Calendar;
use CalendArt\Adapter\Google\Model\User;
use CalendArt\Adapter\Google\Model\UserPermission;
use Doctrine\Common\Collections\ArrayCollection;

use CalendArt\Adapter\Google\Criterion\Field;
use CalendArt\Adapter\Google\Criterion\Collection;
use CalendArt\AbstractCalendar;
use CalendArt\Adapter\CalendarApiInterface;
use CalendArt\Adapter\Calendar\AclInterface;

/**
 * Google Adapter for the Calendars
 *
 * @author Baptiste Clavié <baptiste@wisembly.com>
 */
class CalendarApi implements CalendarApiInterface, AclInterface
{
    /** @var GoogleAdapter Google Adapter used */
    private $adapter;

    private $criteria;

    public function __construct(GoogleAdapter $adapter)
    {
        $this->adapter = $adapter;

        $this->criteria = [new Field('id'),
                           new Field('summary'),
                           new Field('timeZone'),
                           new Field('description')];
    }

    /** {@inheritDoc} */
    public function getList(AbstractCriterion $criterion = null)
    {
        $items = new Field('items', $this->criteria);
        $items->addCriterion(new Field('accessRole'));

        $query = new Collection([new Collection([$items, new Field('nextPageToken'), new Field('nextSyncToken')], 'fields')]);

        if (null !== $criterion) {
            $query = $query->merge($criterion);
        }

        $result = $this->adapter->sendRequest('get', '/calendar/v3/users/me/calendarList', ['query' => $query->build()]);
        $list   = new ArrayCollection;

        foreach ($result['items'] as $item) {
            $list[$item['id']] = Calendar::hydrate($item, $this->adapter->getUser());
        }

        return $list;
    }

    /** {@inheritDoc} */
    public function get($identifier, AbstractCriterion $criterion = null)
    {
        $query = new Collection([new Collection([new Field(null, $this->criteria)], 'fields')]);

        if (null !== $criterion) {
            $query = $query->merge($criterion);
        }

        $response = $this->adapter->sendRequest('get', sprintf('/calendar/v3/calendars/%s', $identifier), ['query' => $query->build()]);

        return Calendar::hydrate($response, $this->adapter->getUser());
    }

    /** {@inheritDoc} */
    public function getPermissions(AbstractCalendar $calendar, AbstractCriterion $criterion = null)
    {
        $query = new Collection([new Collection([new Field('items', [new Field('id'), new Field('scope'), new Field('role')])], 'fields')]);

        if (null !== $criterion) {
            $query = $query->merge($criterion);
        }

        $result = $this->adapter->sendRequest('get', sprintf('/calendar/v3/calendars/%s/acl', $calendar->getId()), ['query' => $query->build()]);
        $list   = new ArrayCollection;

        foreach ($result['items'] as $item) {
            // only user scope are supported
            if ('user' !== $item['scope']['type']) {
                continue;
            }

            $user = in_array($item['scope']['value'], $this->adapter->getUser()->getEmail(true)) ? $this->adapter->getUser() : User::hydrate(['email' => $item['scope']['value']]);

            $list[$item['id']] = UserPermission::hydrate($calendar, $user, $item['role']);
        }

        $calendar->setPermissions($list);

        return $list;
    }

    /** @return GoogleAdapter */
    public function getAdapter()
    {
        return $this->adapter;
    }
}
