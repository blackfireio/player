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

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Application extends BaseApplication
{
    public function __construct()
    {
        error_reporting(-1);

        $version = '@git-version@';
        if ('@'.'git-version@' == $version) {
            $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
            $version = $composer['extra']['branch-alias']['dev-master'];
        }

        parent::__construct('Blackfire Player', $version);

        $this->add(new PlayerCommand());
    }

    public function renderException(\Exception $e, OutputInterface $output)
    {
        if (!\Phar::running()) {
            return parent::renderException($e, $output);
        }

        $output->writeln('', OutputInterface::VERBOSITY_QUIET);
        $lines = ['[ERROR]'];

        $terminal = new Terminal();
        $width = $terminal->getWidth() ? $terminal->getWidth() - 1 : PHP_INT_MAX;
        // HHVM only accepts 32 bits integer in str_split, even when PHP_INT_MAX is a 64 bit integer: https://github.com/facebook/hhvm/issues/1327
        if (defined('HHVM_VERSION') && $width > 1 << 31) {
            $width = 1 << 31;
        }
        foreach (preg_split('/\r?\n/', $e->getMessage()) as $line) {
            foreach ($this->splitStringByWidth($line, $width - 4) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Player documentation at https://blackfire.io/player';

        $output->writeln($this->getHelperSet()->get('formatter')->formatBlock($lines, 'error', true), OutputInterface::VERBOSITY_QUIET);
        $output->writeln('', OutputInterface::VERBOSITY_QUIET);
    }

    // from Symfony\Component\Console\Application
    private function splitStringByWidth($string, $width)
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
        foreach (preg_split('//u', $utf8String) as $char) {
            // test if $char could be appended to current line
            if (mb_strwidth($line.$char, 'utf8') <= $width) {
                $line .= $char;
                continue;
            }
            // if not, push current line to array and make new line
            $lines[] = str_pad($line, $width);
            $line = $char;
        }
        if ('' !== $line) {
            $lines[] = count($lines) ? str_pad($line, $width) : $line;
        }

        mb_convert_variables($encoding, 'utf8', $lines);

        return $lines;
    }
}
