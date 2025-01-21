<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Console;

use Blackfire\Player\Adapter\BlackfireSdkAdapterInterface;
use Blackfire\Player\Player;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class Application extends BaseApplication
{
    public function __construct(BlackfireSdkAdapterInterface|null $blackfireSdk, HttpClientInterface|null $blackfireHttpClient, string $transactionId)
    {
        parent::__construct('Blackfire Player', Player::version());

        $this->add(new PlayerCommand($blackfireHttpClient, $blackfireSdk, $transactionId));
        $this->add(new ValidateCommand());
    }

    public function renderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);
        $lines = ['[ERROR]'];

        $terminal = new Terminal();
        $width = 0 !== $terminal->getWidth() ? $terminal->getWidth() - 1 : \PHP_INT_MAX;

        foreach (preg_split('/\r?\n/', $e->getMessage()) as $line) {
            foreach ($this->splitStringByWidth($line, $width - 4) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Player documentation at https://blackfire.io/player';

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelperSet()->get('formatter');
        $output->writeln($formatter->formatBlock($lines, 'error', true), OutputInterface::VERBOSITY_QUIET);
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);

        if ('' !== \Phar::running() && $output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
            parent::renderThrowable($e, $output);
        }
    }

    // from Symfony\Component\Console\Application
    private function splitStringByWidth(string $string, int $width): array
    {
        // str_split is not suitable for multi-byte characters, we should use preg_split to get char array properly.
        // additionally, array_slice() is not enough as some character has doubled width.
        // we need a function to split string not by character count but by string width
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return str_split($string, $width);
        }

        $utf8String = mb_convert_encoding($string, 'utf8', $encoding);
        $lines = [];
        $line = '';

        $offset = 0;
        while (preg_match('/.{1,10000}/u', $utf8String, $m, 0, $offset)) {
            $offset += \strlen($m[0]);

            foreach (preg_split('//u', $m[0]) as $char) {
                // test if $char could be appended to current line
                if (mb_strwidth($line.$char, 'utf8') <= $width) {
                    $line .= $char;
                    continue;
                }
                // if not, push current line to array and make new line
                $lines[] = str_pad($line, $width);
                $line = $char;
            }
        }

        $lines[] = [] !== $lines ? str_pad($line, $width) : $line;

        mb_convert_variables($encoding, 'utf8', $lines);

        return $lines;
    }
}
