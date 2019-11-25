<?php

namespace unreal4u\SuspendBigpapa;

use unreal4u\rpiCommonLibrary\Base;
use unreal4u\rpiCommonLibrary\JobContract;
use Symfony\Component\Console\Command\Command;

final class PassOnStatus extends Base
{
    private const STATS_FILE = '/root/suspend-server/var/uptime-stats.json';

    public function setUp(): JobContract
    {
        return $this;
    }

    public function configure()
    {
        $this
            ->setName('bigpapa:stats')
            ->setDescription('Sends out some statistics about bigpapa')
            ->setHelp('TODO');
    }

    public function runJob(): bool
    {
        $this->gatherStats();
        return true; 
    }

    public function retrieveErrors(): \Generator
    {
        yield '';
    }

    public function forceKillAfterSeconds(): int
    {
        return 3600;
    }

    public function executeEveryMicroseconds(): int
    {
        return 0;
    }

    private function gatherStats(): self
    {
        $mqttCommunicator = $this->communicationsFactory('MQTT');
        $mqttCommunicator->sendMessage('telemetry/bigpapa', json_encode([
            'time' => new \DateTimeImmutable(),
            'status' => 'on',
        ]));
        return $this;
    }
}

