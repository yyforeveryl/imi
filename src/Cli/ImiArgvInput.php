<?php

declare(strict_types=1);

namespace Imi\Cli;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;

class ImiArgvInput extends Input
{
    private array $tokens = [];
    private array $parsed = [];

    /**
     * 是否启用动态参数支持
     */
    private bool $dynamicOptions = false;

    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        $argv = $argv ?? $_SERVER['argv'] ?? [];

        // strip the application name
        array_shift($argv);

        $this->tokens = $argv;

        parent::__construct($definition);
    }

    public function parseByCommand(ImiCommand $command): void
    {
        $optionsDefinition = $command->getOptionsDefinition();
        foreach ($this->options as $name => &$value)
        {
            if (isset($optionsDefinition[$name]) && ArgType::isBooleanType($optionsDefinition[$name]['type']))
            {
                if (null === $value)
                {
                    $value = true;
                }
                else
                {
                    $value = (bool) $value;
                }
            }
        }
    }

    protected function setTokens(array $tokens): void
    {
        $this->tokens = $tokens;
    }

    /**
     * {@inheritdoc}
     */
    protected function parse(): void
    {
        $parseOptions = true;
        $this->parsed = $this->tokens;
        while (null !== $token = array_shift($this->parsed))
        {
            if ($parseOptions && '' == $token)
            {
                $this->parseArgument($token);
            }
            elseif ($parseOptions && '--' == $token)
            {
                $parseOptions = false;
            }
            elseif ($parseOptions && 0 === strpos($token, '--'))
            {
                $this->parseLongOption($token);
            }
            elseif ($parseOptions && '-' === $token[0] && '-' !== $token)
            {
                $this->parseShortOption($token);
            }
            else
            {
                $this->parseArgument($token);
            }
        }
    }

    /**
     * Parses a short option.
     */
    private function parseShortOption(string $token): void
    {
        $name = substr($token, 1);

        if (\strlen($name) > 1)
        {
            if ($this->definition->hasShortcut($name[0]) && $this->definition->getOptionForShortcut($name[0])->acceptValue())
            {
                // an option with a value (with no space)
                $this->addShortOption($name[0], substr($name, 1));
            }
            else
            {
                $this->parseShortOptionSet($name);
            }
        }
        else
        {
            $this->addShortOption($name, null);
        }
    }

    /**
     * Parses a short option set.
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function parseShortOptionSet(string $name): void
    {
        $len = \strlen($name);
        for ($i = 0; $i < $len; ++$i)
        {
            if (!$this->definition->hasShortcut($name[$i]))
            {
                if ($this->dynamicOptions)
                {
                    continue;
                }
                else
                {
                    $encoding = mb_detect_encoding($name, null, true);
                    throw new RuntimeException(sprintf('The "-%s" option does not exist.', false === $encoding ? $name[$i] : mb_substr($name, $i, 1, $encoding)));
                }
            }

            $option = $this->definition->getOptionForShortcut($name[$i]);
            if ($option->acceptValue())
            {
                $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                break;
            }
            else
            {
                $this->addLongOption($option->getName(), null);
            }
        }
    }

    /**
     * Parses a long option.
     */
    private function parseLongOption(string $token): void
    {
        $name = substr($token, 2);

        if (false !== $pos = strpos($name, '='))
        {
            if (0 === \strlen($value = substr($name, $pos + 1)))
            {
                array_unshift($this->parsed, $value);
            }
            $this->addLongOption(substr($name, 0, $pos), $value);
        }
        else
        {
            $this->addLongOption($name, null);
        }
    }

    /**
     * Parses an argument.
     *
     * @throws RuntimeException When too many arguments are given
     */
    private function parseArgument(string $token): void
    {
        $c = \count($this->arguments);

        // if input is expecting another argument, add it
        if ($this->definition->hasArgument($c))
        {
            $arg = $this->definition->getArgument($c);
            $this->arguments[$arg->getName()] = $arg->isArray() ? [$token] : $token;

        // if last argument isArray(), append token to last argument
        }
        elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray())
        {
            $arg = $this->definition->getArgument($c - 1);
            $this->arguments[$arg->getName()][] = $token;

        // unexpected argument
        }
        elseif (!$this->dynamicOptions)
        {
            $all = $this->definition->getArguments();
            $symfonyCommandName = null;
            if (($inputArgument = $all[$key = array_key_first($all)] ?? null) && 'command' === $inputArgument->getName())
            {
                $symfonyCommandName = $this->arguments['command'] ?? null;
                unset($all[$key]);
            }

            if (\count($all))
            {
                if ($symfonyCommandName)
                {
                    $message = sprintf('Too many arguments to "%s" command, expected arguments "%s".', $symfonyCommandName, implode('" "', array_keys($all)));
                }
                else
                {
                    $message = sprintf('Too many arguments, expected arguments "%s".', implode('" "', array_keys($all)));
                }
            }
            elseif ($symfonyCommandName)
            {
                $message = sprintf('No arguments expected for "%s" command, got "%s".', $symfonyCommandName, $token);
            }
            else
            {
                $message = sprintf('No arguments expected, got "%s".', $token);
            }

            throw new RuntimeException($message);
        }
    }

    /**
     * Adds a short option value.
     *
     * @param mixed $value
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addShortOption(string $shortcut, $value): void
    {
        if (!$this->definition->hasShortcut($shortcut))
        {
            if ($this->dynamicOptions)
            {
                return;
            }
            else
            {
                throw new RuntimeException(sprintf('The "-%s" option does not exist.', $shortcut));
            }
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    /**
     * Adds a long option value.
     *
     * @param mixed $value
     *
     * @throws RuntimeException When option given doesn't exist
     */
    private function addLongOption(string $name, $value): void
    {
        if (!$this->definition->hasOption($name))
        {
            if ($this->dynamicOptions)
            {
                return;
            }

            if (!$this->definition->hasNegation($name))
            {
                throw new RuntimeException(sprintf('The "--%s" option does not exist.', $name));
            }

            $optionName = $this->definition->negationToName($name);
            if (null !== $value)
            {
                throw new RuntimeException(sprintf('The "--%s" option does not accept a value.', $name));
            }
            $this->options[$optionName] = false;

            return;
        }

        $option = $this->definition->getOption($name);

        if (null !== $value && !$option->acceptValue())
        {
            throw new RuntimeException(sprintf('The "--%s" option does not accept a value.', $name));
        }

        if (\in_array($value, ['', null], true) && $option->acceptValue() && \count($this->parsed))
        {
            // if option accepts an optional or mandatory argument
            // let's see if there is one provided
            $next = array_shift($this->parsed);
            if ((isset($next[0]) && '-' !== $next[0]) || \in_array($next, ['', null], true))
            {
                $value = $next;
            }
            else
            {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value)
        {
            if ($option->isValueRequired())
            {
                throw new RuntimeException(sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->isArray() && !$option->isValueOptional())
            {
                $value = true;
            }
        }

        if ($option->isArray())
        {
            $this->options[$name][] = $value;
        }
        else
        {
            $this->options[$name] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstArgument()
    {
        $isOption = false;
        foreach ($this->tokens as $i => $token)
        {
            if ($token && '-' === $token[0])
            {
                if (false !== strpos($token, '=') || !isset($this->tokens[$i + 1]))
                {
                    continue;
                }

                // If it's a long option, consider that everything after "--" is the option name.
                // Otherwise, use the last char (if it's a short option set, only the last one can take a value with space separator)
                $name = '-' === $token[1] ? substr($token, 2) : substr($token, -1);
                if (!isset($this->options[$name]) && !$this->definition->hasShortcut($name))
                {
                    // noop
                }
                elseif ((isset($this->options[$name]) || isset($this->options[$name = $this->definition->shortcutToName($name)])) && $this->tokens[$i + 1] === $this->options[$name])
                {
                    $isOption = true;
                }

                continue;
            }

            if ($isOption)
            {
                $isOption = false;
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameterOption($values, bool $onlyParams = false)
    {
        $values = (array) $values;

        foreach ($this->tokens as $token)
        {
            if ($onlyParams && '--' === $token)
            {
                return false;
            }
            foreach ($values as $value)
            {
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = 0 === strpos($value, '--') ? $value . '=' : $value;
                if ($token === $value || '' !== $leading && 0 === strpos($token, $leading))
                {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterOption($values, $default = false, bool $onlyParams = false)
    {
        $values = (array) $values;
        $tokens = $this->tokens;

        while (0 < \count($tokens))
        {
            $token = array_shift($tokens);
            if ($onlyParams && '--' === $token)
            {
                return $default;
            }

            foreach ($values as $value)
            {
                if ($token === $value)
                {
                    return array_shift($tokens);
                }
                // Options with values:
                //   For long options, test for '--option=' at beginning
                //   For short options, test for '-o' at beginning
                $leading = str_starts_with($value, '--') ? $value . '=' : $value;
                if ('' !== $leading && str_starts_with($token, $leading))
                {
                    return substr($token, \strlen($leading));
                }
            }
        }

        return $default;
    }

    /**
     * Returns a stringified representation of the args passed to the command.
     *
     * @return string
     */
    public function __toString()
    {
        $tokens = array_map(function (string $token): string {
            if (preg_match('{^(-[^=]+=)(.+)}', $token, $match))
            {
                return $match[1] . $this->escapeToken($match[2]);
            }

            if ($token && '-' !== $token[0])
            {
                return $this->escapeToken($token);
            }

            return $token;
        }, $this->tokens);

        return implode(' ', $tokens);
    }

    public function getDynamicOptions(): bool
    {
        return $this->dynamicOptions;
    }

    public function setDynamicOptions(bool $dynamicOptions): self
    {
        $this->dynamicOptions = $dynamicOptions;

        return $this;
    }
}
