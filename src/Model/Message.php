<?php

namespace CalendArt\Adapter\Google\Model;

use CalendArt\AbstractMessage;

class Message extends AbstractMessage
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $name
     * @param null $default
     * @return null
     */
    public function getHeader($name, $default = null)
    {
        $foundHeader = $default;
        foreach($this->headers as $header) {
            if($header['name'] == $name) {
                $foundHeader = $header['value'];
            }
        }
        return $foundHeader;
    }

    /**
     * @param array $data
     * @return Message
     */
    public static function hydrate(array $data)
    {
        $message = new static();

        // id
        $message->id = $data['id'];

        // preview
        $message->preview = $data['snippet'];

        // sendDate
        $sendDate = new \DateTime();
        $sendDate->setTimestamp($data['internalDate']);
        $message->sentDate = $sendDate;

        // payload
        $payload = isset($data['payload']) ? $data['payload'] : [];

        // headers
        $message->headers = isset($payload['headers']) ? $payload['headers'] : [];

        $message->subject = $message->getHeader('Subject');
        $message->sender = $message->hydrateUserFromHeaderValue($message->getHeader('From'));

        // body
        if (isset($payload['mimeType'])) {
            if ($payload['mimeType'] === 'multipart/alternative') {
                $message->htmlBody = $message->getBodyFromMultiPartAlternative($payload['parts'], 'text/html');
                $message->textBody = $message->getBodyFromMultiPartAlternative($payload['parts'], 'text/plain');
            } elseif ($payload['mimeType'] === 'multipart/mixed') {

            }
        }


        return $message;
    }


    /**
     * @param $value
     * @return User
     */
    private function hydrateUserFromHeaderValue($value)
    {
        $matches = preg_match('/\s*(.*[^\s])\s*<\s*(.*[^\s])\s*>/', $value);

        $name = isset($matches[1]) ? $matches[1] : '';
        $email = isset($matches[2]) ? $matches[2] : '';

        return new User($name, $email);
    }

    /**
     * @param array $parts
     * @param string $mimeType
     * @param null $default
     * @return null|string
     */
    private function getBodyFromMultiPartAlternative(array $parts, $mimeType, $default = null)
    {
        foreach ($parts as $part) {
            if ($part['mimeType'] === $mimeType) {
                return $this->decodeBody($part['body']['data'], $default);
            }
        }

        return $default;
    }

    /**
     * @param $body
     * @param null $default
     * @return null|string
     */
    private function decodeBody($body, $default = null) {
        $sanitizedData = strtr($body, '-_', '+/');

        $decodedMessage = base64_decode($sanitizedData);

        if(!$decodedMessage){
            return $default;
        }

        return $decodedMessage;
    }
}