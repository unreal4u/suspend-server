<?php

namespace unreal4u\SuspendBigpapa;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReadMQTTBroker extends Command
{
    private $rxBytesPrevious = 0;

    private $txBytesPrevious = 0;

    private $rxBytesCurrent = 0;

    private $txBytesCurrent = 0;

    private const NETWORK_INTERFACE = 'internet';

    private const STATS_FILE = '/root/suspend-server/var/stats.json';

    public function __construct()
    {
        $simpleName = substr(strrchr(get_class($this), '\\'), 1);
        parent::__construct($simpleName);
    }

    public function configure()
    {
        $this
            ->setName('command:suspend-bigpapa')
            ->setDescription('Checks whether to suspend bigpapa')
            ->setHelp('TODO');
    }

    public function execute(InputInterface $input, OutputInterface $output): self
    {
        $this->retrievePreviousNetworkStats();
        $this->retrieveCurrentNetworkStats();

        $this->performCalculation();
        return $this;
    }

    private function performCalculation(): self
    {
        $suspendProbability = 2;

        var_dump('rx', abs($this->rxBytesCurrent - $this->rxBytesPrevious), abs($this->rxBytesCurrent - $this->rxBytesPrevious) <= 5000000 * 5);
        if (abs($this->rxBytesCurrent - $this->rxBytesPrevious) <= 5000000 * 5) {
            $suspendProbability--;
        }

        var_dump('tx', abs($this->txBytesCurrent - $this->txBytesPrevious), abs($this->txBytesCurrent - $this->txBytesPrevious) <= 5000000 * 5);
        if (abs($this->txBytesCurrent - $this->txBytesPrevious) <= 5000000 * 5) {
            $suspendProbability--;
        }

        if ($suspendProbability === 0) {
            echo 'Executing automatic suspension' . PHP_EOL;
            #exec('systemctl suspend');
        } else {
            echo 'System is being actively used (Counter is: ' . $suspendProbability . ')';
        }
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

