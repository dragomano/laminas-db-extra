<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Adapter;

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Adapter\Profiler\Profiler;
use Laminas\Db\Adapter\StatementContainerInterface;
use Laminas\Db\Sql\Exception\InvalidArgumentException;

class ExtendedProfiler extends Profiler
{
    public function __construct(protected ?PlatformInterface $platform = null) {}

    public function profilerStart($target): self
    {
        $profileInformation = [
            'sql'        => '',
            'parameters' => null,
            'start'      => microtime(true),
            'end'        => null,
            'elapse'     => null,
            'backtrace'  => $this->captureBacktrace(),
        ];

        if ($target instanceof StatementContainerInterface) {
            if ($this->platform !== null) {
                $profileInformation['sql'] = $this->getRealSqlFromStatement($target);
            } else {
                $profileInformation['sql'] = $target->getSql();
            }

            $profileInformation['parameters'] = clone $target->getParameterContainer();
        } elseif (is_string($target)) {
            $profileInformation['sql'] = $target;
        } else {
            throw new InvalidArgumentException(
                __FUNCTION__ . ' takes either a StatementContainer or a string'
            );
        }

        $this->profiles[$this->currentIndex] = $profileInformation;

        return $this;
    }

    protected function captureBacktrace(): ?array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        $relevantFrame = null;
        foreach ($backtrace as $frame) {
            if (! $this->isFrameIgnored($frame) && $this->isFrameRelevant($frame)) {
                $relevantFrame = $frame;

                break;
            }
        }

        if ($relevantFrame) {
            return [
                'file'       => $relevantFrame['file'] ?? 'unknown',
                'line'       => $relevantFrame['line'] ?? 0,
                'function'   => $relevantFrame['function'] ?? 'unknown',
                'full_trace' => array_slice($backtrace, 0, 5),
            ];
        }

        return null;
    }

    protected function isFrameIgnored(array $frame): bool
    {
        $file     = $frame['file'] ?? '';
        $function = $frame['function'] ?? '';
        $class    = $frame['class'] ?? '';

        return str_contains($function, 'profiler')
            || str_contains($file, 'Profiler')
            || str_contains($class, 'Laminas')
            || str_starts_with($file, '/usr/')
            || str_starts_with($file, 'phar://')
            || $function === 'captureBacktrace';
    }

    protected function isFrameRelevant(array $frame): bool
    {
        return isset($frame['file'])
            && $frame['file'] !== ''
            && isset($frame['class'])
            && $frame['class'] !== '';
    }

    protected function getRealSqlFromStatement(StatementContainerInterface $statement): string
    {
        $sql    = $statement->getSql();
        $params = $statement->getParameterContainer()?->getNamedArray() ?? [];

        uksort($params, fn($a, $b): int => strlen((string) $b) <=> strlen((string) $a));

        foreach ($params as $key => $value) {
            $placeholder = ':' . $key;

            if ($value === null) {
                $escapedValue = 'NULL';
            } else {
                $escapedValue = $this->platform->quoteTrustedValue($value);
            }

            $sql = str_replace($placeholder, $escapedValue, $sql);
        }

        $sql = preg_replace("/LIMIT\s+'?(\d+)'?/", 'LIMIT $1', (string) $sql);
        $sql = preg_replace("/OFFSET\s+'?(\d+)'?/", 'OFFSET $1', (string) $sql);
        $sql = preg_replace('/`([^`]+)`/', '$1', (string) $sql);

        return $this->formatSql($sql);
    }

    protected function formatSql(string $sql, int $indentLevel = 0): string
    {
        $sql       = preg_replace('/\s+/', ' ', trim($sql));
        $indent    = str_repeat('    ', $indentLevel);
        $subIndent = str_repeat('    ', $indentLevel + 1);

        $sql = preg_replace_callback(
            '/(SELECT)\s+(.*?)(\s+FROM)/is',
            function ($matches) use ($indent, $subIndent) {
                $selectPart = trim($matches[2]);
                $columns = $this->splitColumnsSmart($selectPart);

                if (count($columns) <= 2 && strlen($selectPart) < 100) {
                    return $indent . $matches[1] . ' ' . $selectPart . $matches[3];
                }

                $formatted = $indent . $matches[1] . "\n";
                $formatted .= $subIndent . implode(",\n" . $subIndent, $columns);

                return $formatted . $matches[3];
            },
            (string) $sql
        );

        $keywords = [
            'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET',
            'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'OUTER JOIN', 'CROSS JOIN',
        ];

        foreach ($keywords as $keyword) {
            $sql = preg_replace(
                '/\s+' . preg_quote($keyword, '/') . '\s+/i',
                "\n" . $indent . $keyword . ' ',
                (string) $sql
            );
        }

        $sql = preg_replace_callback(
            '/(\s+)(AND|OR)(\s+)(?![^()]*\))/i',
            function ($matches) use ($subIndent) {
                $operator = strtoupper($matches[2]);

                return "\n" . $subIndent . $operator . ' ';
            },
            (string) $sql
        );

        $sql = preg_replace_callback(
            '/((?:LEFT|RIGHT|INNER|OUTER|CROSS)?\s*JOIN\s+.*?\s+ON\s+.*?)(\n\s+AND\s+)/is',
            fn($matches): string => $matches[1] . "\n" . $subIndent . 'AND ',
            (string) $sql
        );

        $sql = preg_replace_callback(
            '/IN\s*\((.*?)\)/is',
            function ($matches) {
                $content = preg_replace('/\s+/', ' ', $matches[1]);

                return 'IN (' . trim($content) . ')';
            },
            (string) $sql
        );

        return $this->formatSubqueries($sql, $indentLevel);
    }

    protected function splitColumnsSmart(string $selectPart): array
    {
        $columns       = [];
        $currentColumn = '';
        $parenCount    = 0;
        $inString      = false;
        $stringChar    = '';

        for ($i = 0; $i < strlen($selectPart); $i++) {
            $char     = $selectPart[$i];
            $prevChar = $i === 0 ? '' : $selectPart[$i - 1];

            $this->updateStringAndParenState($char, $prevChar, $inString, $stringChar, $parenCount);

            if ($char === ',' && $parenCount === 0 && ! $inString) {
                $columns[]     = trim($currentColumn);
                $currentColumn = '';
            } else {
                $currentColumn .= $char;
            }
        }

        if ($currentColumn !== '') {
            $columns[] = trim($currentColumn);
        }

        return $columns;
    }

    protected function updateStringAndParenState(
        string $char,
        string $prevChar,
        bool &$inString,
        string &$stringChar,
        int &$parenCount
    ): void {
        if (($char === "'" || $char === '"') && $prevChar !== '\\') {
            if (! $inString) {
                $inString   = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
        }

        if (! $inString) {
            if ($char === '(') {
                $parenCount++;
            } elseif ($char === ')') {
                $parenCount--;
            }
        }
    }

    protected function formatSubqueries(string $sql, int $indentLevel): string
    {
        $offset = 0;
        while (($openPos = strpos($sql, '(SELECT', $offset)) !== false) {
            $closePos = $this->findClosingBracket($sql, $openPos);

            if ($closePos !== -1) {
                $subquery  = substr($sql, $openPos + 1, $closePos - $openPos - 1);
                $formatted = $this->formatSql($subquery, $indentLevel + 1);
                $subIndent = str_repeat('    ', $indentLevel + 1);

                $lines             = explode("\n", $formatted);
                $indentedLines     = array_map(fn($line): string => $subIndent . $line, $lines);
                $formattedSubquery = implode("\n", $indentedLines);
                $replacement       = "(\n" . $formattedSubquery . "\n" . $subIndent . ')';

                $sql = substr_replace($sql, $replacement, $openPos, $closePos - $openPos + 1);

                $offset = $openPos + strlen($replacement);
            } else {
                $offset = $openPos + 1;
            }
        }

        return $sql;
    }

    protected function findClosingBracket(string $sql, int $openPos): int
    {
        $parenCount = 1;
        $closePos   = $openPos + 1;
        $inString   = false;
        $stringChar = '';

        while ($parenCount > 0 && $closePos < strlen($sql)) {
            $char     = $sql[$closePos];
            $prevChar = $closePos === 0 ? '' : $sql[$closePos - 1];

            $this->updateStringAndParenState($char, $prevChar, $inString, $stringChar, $parenCount);

            $closePos++;
        }

        return $parenCount === 0 ? $closePos - 1 : -1;
    }
}
