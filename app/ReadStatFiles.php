<?php

namespace unreal4u\SuspendBigpapa;

use unreal4u\rpiCommonLibrary\Base;
use unreal4u\rpiCommonLibrary\JobContract;
use Symfony\Component\Console\Command\Command;

final class ReadStatFiles extends Base
{
    private $rxBytesPrevious = 0;

    private $txBytesPrevious = 0;

    private $rxBytesCurrent = 0;

    private $txBytesCurrent = 0;

    private const NETWORK_INTERFACE = 'internet';

    private const STATS_FILE = '/root/suspend-server/var/stats.json';

    private const WAKE_FILE = '/root/suspend-server/var/last-wakeup';

    public function setUp(): JobContract
    {
        return $this;
    }

    public function configure()
    {
        $this
            ->setName('bigpapa:check-automatic-suspension')
            ->setDescription('Checks whether to suspend bigpapa')
            ->setHelp('TODO');
    }

    public function runJob(): bool
    {
        // TODO If machine hasn't been awaken for at least half an hour, nothing is needed
        $this->retrievePreviousNetworkStats();
        $this->retrieveCurrentNetworkStats();

        $this->performCalculation();
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

    /**
     * Performs all the necesary calculations to determine whether to suspend the system or not
     *
     * TODO This could be definitively be better, refactor it
     */
    private function performCalculation(): self
    {
        // Change this to some more elegant (bitwise?) solution
        $suspendProbability = 4;

        if ($this->awakenForAtLeastHalfHour() === true) {
            $suspendProbability--;
        }

        // Counting about 5MB per minute, this script is supposed to run every 15 minutes
        if (abs($this->rxBytesCurrent - $this->rxBytesPrevious) <= 5000000 * 15) {
            $suspendProbability--;
        }

        // Counting about 5MB per minute, this script is supposed to run every 15 minutes
        if (abs($this->txBytesCurrent - $this->txBytesPrevious) <= 5000000 * 15) {
            $suspendProbability--;
        }

        if ($this->getNumberOpenShells() === 0) {
            $suspendProbability--;
        }

        if ($suspendProbability === 0) {
            $this->suspendSystem();
        } else {
            $this->logger->debug('System is being actively used', ['suspendCounter' => $suspendProbability]);
        }
        return $this;
    }

    private function suspendSystem(): bool
    {
        $this->logger->info(
            'Executing automatic suspension',
            [
                'tx' => abs($this->txBytesCurrent - $this->txBytesPrevious),
                'rx' => abs($this->rxBytesCurrent - $this->rxBytesPrevious),
            ]
        );
        $this->sendMQTTStatus();
        // Give about 5 seconds to let the message go through
        sleep(5);
        exec('systemctl suspend');

        return true;
    }

    /**
     * Checks whether there are open SSH / SFTP connections. Requires root to run
     */
    private function getNumberOpenShells(): int
    {
        return (int) exec('netstat -tnpa | grep \'ESTABLISHED.*sshd\' | wc -l');
    }

    /**
     * If the server just awoke, I might still be busy chosing which film to play
     */
    private function awakenForAtLeastHalfHour(): bool
    {
        $lastWakeup = new \DateTimeImmutable(file_get_contents(self::WAKE_FILE));
        $currentDate = new \DateTimeImmutable();

        $difference = $currentDate->diff($lastWakeup);
        $minutes = $difference->days * 24 * 60;
        $minutes += $difference->h * 60;
        $minutes += $difference->i;

        if ($minutes > 30) {
            return true;
        }

        return false;
    }

    private function sendMQTTStatus(): self
    {
        $mqttCommunicator = $this->communicationsFactory('MQTT');
        $mqttCommunicator->sendMessage('status/bigpapa', 'off');
        $mqttCommunicator->sendMessage('commands/bigpapa', 'off');
        return $this;
    }

    private function retrieveCurrentNetworkStats(): self
    {
        $this->txBytesCurrent = (int) trim(file_get_contents('/sys/class/net/' . self::NETWORK_INTERFACE . '/statistics/tx_bytes'));
        $this->rxBytesCurrent = (int) trim(file_get_contents('/sys/class/net/' . self::NETWORK_INTERFACE . '/statistics/rx_bytes'));

        $structure = [
            'currentDate' => new \DateTimeImmutable(),
            'rxBytes' => $this->rxBytesCurrent,
            'txBytes' => $this->txBytesCurrent,
        ];
        file_put_contents(self::STATS_FILE, json_encode($structure));

        return $this;
    }

    private function retrievePreviousNetworkStats(): self
    {
        if (is_readable(self::STATS_FILE)) {
            $results = json_decode(file_get_contents(self::STATS_FILE), true);

            $this->rxBytesPrevious = $results['rxBytes'];
            $this->txBytesPrevious = $results['txBytes'];
        }

        return $this;
    }
}

