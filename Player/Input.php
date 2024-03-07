<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Exception\SyntaxErrorException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class Input
{
    /** @var string[] */
    private array $lines;
    private int $lineno = 0;
    private int $max;

    public function __construct(
        string $input,
        private readonly string|null $file = null,
    ) {
        $this->lines = $this->splitInput($input);

        if ($this->isEof()) {
            throw new SyntaxErrorException(sprintf('You must define at least one step in file %s.', $file));
        }
    }

    public function isEof(): bool
    {
        return $this->findNextLine() > $this->max;
    }

    public function getNextLine(): string
    {
        while ($this->max >= $this->lineno = $this->findNextLine()) {
            return ltrim($this->lines[$this->lineno]);
        }

        return '';
    }

    public function getCurrentLine(): string
    {
        return $this->lines[$this->lineno];
    }

    public function getNextLineIndent(): int
    {
        return $this->computeIndent($this->findNextLine());
    }

    public function rewindLine(): void
    {
        --$this->lineno;
    }

    public function getIndent(): int
    {
        return $this->computeIndent($this->lineno);
    }

    public function getFile(): string|null
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->lineno;
    }

    public function getContextString(): string
    {
        if ($this->file) {
            return sprintf('in %s at line %d', $this->file, $this->lineno);
        }

        return sprintf('at line %d', $this->lineno);
    }

    private function findNextLine(): int
    {
        $lineno = $this->lineno;
        while (++$lineno <= $this->max) {
            if (!$this->isEmpty($lineno)) {
                return $lineno;
            }
        }

        return $this->max + 1;
    }

    private function isEmpty(int $lineno): bool
    {
        // skip lines that do not exit
        // possible when using line continuation
        return !isset($this->lines[$lineno]);
    }

    /**
     * @return string[]
     */
    private function splitInput(string $input): array
    {
        // normalize newlines
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        $input = explode("\n", $input);

        $current = 0;
        $lineno = 0;
        $lines = [];
        while (null !== $line = array_shift($input)) {
            ++$lineno;

            if (preg_match('/^(\s*)"""(i)?$/', $line, $matches)) { // Start multi-lines
                $modifiers = str_split(ltrim($line, ' "'), 1);
                $indent = $matches[1];
                $val = '';
                while (null !== $line = array_shift($input)) {
                    ++$lineno;

                    if (\strlen($indent) && !str_starts_with($line, $indent)) {
                        throw new SyntaxErrorException(sprintf('Incorrect indentation in multi-lines string at line %d.', $lineno));
                    }

                    if (preg_match('/^(\s*)"""$/', $line, $matchesEnd)) { // end multi-lines
                        if ($matchesEnd[1] === $indent) {
                            if ('' !== $val) {
                                $val = substr($val, 0, -1);
                            }
                            break;
                        }
                    }

                    $val .= substr($line, \strlen($indent))."\n";
                }

                $escaped = ' '.$this->escapeValue($val);
                if (\in_array('i', $modifiers, true)) {
                    $lines[$current] .= preg_replace('/(?<!\\\\)\\$\{\s*('.Parser::REGEX_NAME.')\s*\}/', "' ~ $1 ~ '", $escaped);
                } else {
                    $lines[$current] .= $escaped;
                }

                continue;
            }

            // skip empty lines and comments
            if (preg_match('/^\s*(#|$)/', $line)) {
                continue;
            }

            // remove trailing spaces
            $lines[$lineno] = rtrim($line, " \t\0\x0B\\");

            // line continuations
            $current = $lineno;
            while (preg_match('{\\\s*$}', $line)) {
                while (null !== $line = array_shift($input)) {
                    ++$lineno;

                    // skip empty lines and comments
                    if (!preg_match('/^\s*(#|$)/', $line)) {
                        break;
                    }
                }

                $lines[$current] .= ' '.trim($line, " \t\0\x0B\\");
            }
        }

        $this->max = $lineno;

        return $lines;
    }

    private function computeIndent(int $lineno): int
    {
        $line = $this->lines[$lineno];
        $indent = 0;

        if (preg_match('/^((?:    )+)(.+)$/', $line, $matches)) {
            // spaces in groups of 4
            $indent = \strlen($matches[1]) / 4;
            $line = $matches[2];
        } elseif (preg_match('/^(\t)+(.+)$/', $line, $matches)) {
            // tabs
            $indent = \strlen($matches[1]);
            $line = $matches[2];
        }

        if (preg_match('/^ +/', $line)) {
            throw new SyntaxErrorException(sprintf('Indentation must use spaces in groups of four in file %s.', $this->getContextString()));
        }

        if (preg_match('/^[ \t]+/', $line)) {
            throw new SyntaxErrorException(sprintf('Indentation cannot contain mixed spaces and tabs in file %s.', $this->getContextString()));
        }

        return $indent;
    }

    private function escapeValue(string $val): string
    {
        return sprintf("'%s'", strtr($val, ["\n" => '\n', "'" => "\\'"]));
    }
}
