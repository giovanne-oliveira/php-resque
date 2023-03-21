<?php

/*
 * This file is part of the php-resque package.
 *
 * (c) Michael Haynes <mike@mjphaynes.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Resque\Console\Command\Socket;

use Resque\Console\Command\Command;
use Resque\Socket\Server;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TCP connect command class
 *
 * @package Resque
 * @author Michael Haynes
 */
final class Connect extends Command
{
    protected function configure(): void
    {
        $this->setName('socket:connect')
            ->setDefinition($this->mergeDefinitions([
                new InputOption('connecthost', null, InputOption::VALUE_OPTIONAL, 'The host to connect to.', '127.0.0.1'),
                new InputOption('connectport', null, InputOption::VALUE_OPTIONAL, 'The port to connect to.', Server::DEFAULT_PORT),
                new InputOption('connecttimeout', 't', InputOption::VALUE_OPTIONAL, 'The connection timeout time (seconds).', 10),
            ]))
            ->setDescription('Connects to a php-resque receiver socket')
            ->setHelp('Connects to a php-resque receiver socket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host    = $this->getConfig('connecthost');
        $port    = $this->getConfig('connectport');
        $timeout = $this->getConfig('connecttimeout');

        $conn    = $host.':'.$port;
        $prompt  = 'php-resque '.$conn.'> ';

        $output->writeln('<comment>Connecting to '.$conn.'...</comment>');

        if (!($fh = @fsockopen('tcp://'.$host, $port, $errno, $errstr, $timeout))) {
            $output->writeln('<error>['.$errno.'] '.$errstr.' host '.$conn.'</error>');
            return self::FAILURE;
        }

        // Set socket timeout to 200ms
        stream_set_timeout($fh, 0, 200 * 1000);

        $stdin = fopen('php://stdin', 'r');

        $prompting = false;

        Server::fwrite($fh, 'shell');

        while (true) {
            if (feof($fh)) {
                $output->writeln('<comment>Connection to '.$conn.' closed.</comment>');
                break;
            }

            $read   = [$fh, $stdin];
            $write  = null;
            $except = null;

            $selected = @stream_select($read, $write, $except, 0);
            if ($selected > 0) {
                foreach ($read as $r) {
                    if ($r == $stdin) {
                        $input = trim(fgets($stdin));

                        if (empty($input)) {
                            $output->write($prompt);
                            $prompting = true;
                        } else {
                            Server::fwrite($fh, $input);
                            $prompting = false;
                        }
                    } elseif ($r == $fh) {
                        $input = '';
                        while (($buffer = fgets($fh, 1024)) !== false) {
                            $input .= $buffer;
                        }

                        if ($prompting) {
                            $output->writeln('');
                        }

                        $output->writeln('<pop>'.trim($input).'</pop>');

                        if (!feof($fh)) {
                            $output->write($prompt);
                            $prompting = true;
                        }
                    }
                }
            }

            // Sleep for 10ms to stop CPU spiking
            usleep(10 * 1000);
        }

        fclose($fh);

        return self::SUCCESS;
    }
}
