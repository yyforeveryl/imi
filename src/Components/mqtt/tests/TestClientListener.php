<?php

declare(strict_types=1);

namespace Imi\MQTT\Test;

use BinSoul\Net\Mqtt\Packet\ConnectResponsePacket;
use BinSoul\Net\Mqtt\Packet\PingResponsePacket;
use BinSoul\Net\Mqtt\Packet\SubscribeResponsePacket;
use BinSoul\Net\Mqtt\Packet\UnsubscribeResponsePacket;
use Imi\MQTT\Client\Contract\IMQTTClientListener;

class TestClientListener implements IMQTTClientListener
{
    /**
     * @var \BinSoul\Net\Mqtt\Packet\ConnectResponsePacket
     */
    private $connectACKResult;

    /**
     * @var array
     */
    private $publishResults;

    /**
     * @var \BinSoul\Net\Mqtt\Packet\SubscribeResponsePacket
     */
    private $subscribeACKResult;

    /**
     * @var \BinSoul\Net\Mqtt\Packet\UnsubscribeResponsePacket
     */
    private $unsubscribeACKResult;

    /**
     * @var \BinSoul\Net\Mqtt\Packet\PingResponsePacket
     */
    private $pingResult;

    /**
     * 连接确认.
     */
    public function connectACK(\Imi\MQTT\Client\MQTTClient $client, ConnectResponsePacket $packet): void
    {
        $this->connectACKResult = $packet;
    }

    /**
     * 发布.
     */
    public function publish(\Imi\MQTT\Client\MQTTClient $client, \BinSoul\Net\Mqtt\Packet\PublishRequestPacket $packet): void
    {
        $this->publishResults[$packet->getPacketType()] = $packet;
    }

    /**
     * 发布确认.
     */
    public function publishAck(\Imi\MQTT\Client\MQTTClient $client, \BinSoul\Net\Mqtt\Packet\PublishAckPacket $packet): void
    {
        $this->publishResults[$packet->getPacketType()] = $packet;
    }

    /**
     * 发布已收到（保证交付部分1）.
     */
    public function publishReceived(\Imi\MQTT\Client\MQTTClient $client, \BinSoul\Net\Mqtt\Packet\PublishReceivedPacket $packet): void
    {
        $this->publishResults[$packet->getPacketType()] = $packet;
    }

    /**
     * 发布释放（确保交付的第2部分）.
     */
    public function publishRelease(\Imi\MQTT\Client\MQTTClient $client, \BinSoul\Net\Mqtt\Packet\PublishReleasePacket $packet): void
    {
        $this->publishResults[$packet->getPacketType()] = $packet;
    }

    /**
     * 发布完成（保证交付的第3部分）.
     */
    public function publishComplete(\Imi\MQTT\Client\MQTTClient $client, \BinSoul\Net\Mqtt\Packet\PublishCompletePacket $packet): void
    {
        $this->publishResults[$packet->getPacketType()] = $packet;
    }

    /**
     * 订阅确认.
     */
    public function subscribeACK(\Imi\MQTT\Client\MQTTClient $client, SubscribeResponsePacket $packet): void
    {
        $this->subscribeACKResult = $packet;
    }

    /**
     * 取消订阅确认.
     */
    public function unsubscribeACK(\Imi\MQTT\Client\MQTTClient $client, UnsubscribeResponsePacket $packet): void
    {
        $this->unsubscribeACKResult = $packet;
        $client->disconnect();
    }

    /**
     * Ping 响应.
     */
    public function ping(\Imi\MQTT\Client\MQTTClient $client, PingResponsePacket $packet): void
    {
        $this->pingResult = $packet;
    }

    /**
     * Get the value of connectACKResult.
     */
    public function getConnectACKResult(): ConnectResponsePacket
    {
        return $this->connectACKResult;
    }

    /**
     * Get the value of subscribeACKResult.
     */
    public function getSubscribeACKResult(): SubscribeResponsePacket
    {
        return $this->subscribeACKResult;
    }

    /**
     * Get the value of unsubscribeACKResult.
     */
    public function getUnsubscribeACKResult(): UnsubscribeResponsePacket
    {
        return $this->unsubscribeACKResult;
    }

    /**
     * Get the value of pingResult.
     */
    public function getPingResult(): PingResponsePacket
    {
        return $this->pingResult;
    }

    /**
     * Get the value of publishResults.
     */
    public function getPublishResults(): array
    {
        return $this->publishResults;
    }
}
