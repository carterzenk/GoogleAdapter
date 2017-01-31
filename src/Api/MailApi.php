<?php

namespace CalendArt\Adapter\Google\Api;

use CalendArt\Adapter\Google\GoogleAdapter;
use CalendArt\AbstractMessage;
use CalendArt\Adapter\Google\Model\Message;
use CalendArt\Adapter\MailApiInterface;
use CalendArt\MessageSet;

class MailApi implements MailApiInterface
{
    /**
     * @var GoogleAdapter
     */
    private $adapter;

    /**
     * MailApi constructor.
     * @param GoogleAdapter $adapter
     */
    public function __construct(GoogleAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @inheritdoc
     */
    public function getList($search, $pageToken)
    {
        // TODO: Implement getList function.
    }

    /**
     * @inheritdoc
     */
    public function get($identifier)
    {
        $response = $this->adapter->sendRequest('get', sprintf('/gmail/v1/users/me/messages/%s', $identifier));
        return Message::hydrate($response);
    }
}