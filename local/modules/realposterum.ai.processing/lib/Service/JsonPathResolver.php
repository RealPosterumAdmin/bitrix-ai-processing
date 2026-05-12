<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RuntimeException;

final class JsonPathResolver
{
    public function validate(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('JSON path не должен быть пустым.');
        }

        if (!preg_match('/^[A-Za-z0-9_\-\.\[\]]+$/', $path)) {
            throw new RuntimeException('JSON path содержит недопустимые символы: ' . $path);
        }

        if (str_contains($path, '..') || str_contains($path, '[]') || str_contains($path, '[[') || str_contains($path, ']]')) {
            throw new RuntimeException('JSON path содержит пустые или повторяющиеся сегменты: ' . $path);
        }
    }

    public function exists(array $data, string $path): bool
    {
        try {
            $this->get($data, $path);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function get(array $data, string $path): mixed
    {
        $segments = $this->segments($path);
        $current = $data;
        foreach ($segments as $segment) {
            if (is_int($segment)) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    throw new RuntimeException('JSON path не найден: ' . $path);
                }
                $current = $current[$segment];
                continue;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new RuntimeException('JSON path не найден: ' . $path);
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function set(array &$data, string $path, mixed $value): void
    {
        $segments = $this->segments($path);
        $cursor = &$data;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $isLast = $index === $lastIndex;
            if ($isLast) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }

    /**
     * @return array<int, int|string>
     */
    private function segments(string $path): array
    {
        $this->validate($path);
        $segments = [];
        foreach (explode('.', $path) as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_\-]+)((\[[0-9]+\])*)$/', $chunk, $matches) !== 1) {
                throw new RuntimeException('Некорректный JSON path: ' . $path);
            }

            $segments[] = $matches[1];
            if (!empty($matches[2])) {
                preg_match_all('/\[([0-9]+)\]/', $matches[2], $indexes);
                foreach ($indexes[1] as $index) {
                    $segments[] = (int) $index;
                }
            }
        }

        if ($segments === []) {
            throw new RuntimeException('Некорректный JSON path: ' . $path);
        }

        return $segments;
    }
}
