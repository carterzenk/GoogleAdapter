<?php

namespace CalendArt\Adapter\Google\Test\Model;

use CalendArt\Adapter\Google\Model\Message;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    public function testHydrateRequiredData()
    {
        $message = Message::hydrate($this->getRequiredData());

        $this->assertEquals('159d80792a8c4957', $message->getId());
        $this->assertEquals('Critical things to know about new construction homes.', $message->getPreview());
        $this->assertNotNull($message->getSentDate());
        $this->assertEquals(1485388091000, $message->getSentDate()->getTimestamp());
        $this->assertEquals('Example User', $message->getSender()->getName());
        $this->assertEquals('example@user.com', $message->getSender()->getEmail());
    }

    public function testHydrateBodyFromMultipartMixed()
    {

    }

    public function testHydrateBodyFromMultipartAlternative()
    {
        $message = Message::hydrate($this->getMultipartAlternativeData());
        $this->assertEquals('test', $message->getTextBody());
        $this->assertEquals('<div dir="ltr">test</div>', $message->getHtmlBody());
    }

    protected function getRequiredData()
    {
        return [
            "id" => "159d80792a8c4957",
            "snippet" => "Critical things to know about new construction homes.",
            "historyId" => "350911",
            "internalDate" => "1485388091000",
            "payload" => [
                'headers' => [
                    [
                        'name' => 'Subject',
                        'value' => 'test'
                    ],
                    [
                        'name' => 'From',
                        'value' => 'Example User <example@user.com>'
                    ],
                    [
                        'name' => 'To',
                        'value' => 'Example Recipient <example@recipient.com>'
                    ]
                ]
            ]
        ];
    }

    protected function getMultipartAlternativeData()
    {
        $requiredData = $this->getRequiredData();
        $requiredData['payload']['mimeType'] = 'multipart/alternative';
        $requiredData['payload']['parts'] = [
            [
                'partId' => '0',
                'mimeType' => 'text/plain',
                'filename' => '',
                'headers' => [
                    [
                        'name' => 'Content-Type',
                        'value' => 'text/plain; charset=UTF-8'
                    ]
                ],
                'body' => [
                    'size' => 6,
                    'data' => "dGVzdA0K"
                ]
            ],
            [
                'partId' => '1',
                'mimeType' => 'text/html',
                'filename' => '',
                'headers' => [
                    [
                        'name' => 'Content-Type',
                        'value' => 'text/html; charset=UTF-8'
                    ]
                ],
                'body' => [
                    'size' => 27,
                    'data' => "PGRpdiBkaXI9Imx0ciI-dGVzdDwvZGl2Pg0K"
                ]
            ]
        ];

        return $requiredData;
    }
}