<?php

namespace App\Support;

class EmailBodyEncoding
{
    /**
     * @return list<string>
     */
    private const FallbackCharsets = [
        'CP1251',
        'Windows-1251',
        'ISO-8859-1',
        'ISO-8859-2',
    ];

    public function charsetFromStructure(object $structure): ?string
    {
        foreach ($this->structureParameterSets($structure) as $parameters) {
            $charset = $this->charsetFromParameters($parameters);

            if ($charset !== null) {
                return $charset;
            }
        }

        return null;
    }

    public function toUtf8(string $body, ?string $charset = null): string
    {
        if ($body === '') {
            return $body;
        }

        if ($this->isValidUtf8($body)) {
            return $body;
        }

        foreach ($this->candidateCharsets($charset) as $candidate) {
            $converted = $this->convert($body, $candidate);

            if ($converted !== null) {
                return $converted;
            }
        }

        return $this->stripInvalidUtf8Sequences($body);
    }

    /**
     * @return list<array<int, object>>
     */
    private function structureParameterSets(object $structure): array
    {
        $sets = [];

        if (isset($structure->parameters) && is_array($structure->parameters)) {
            $sets[] = $structure->parameters;
        }

        if (isset($structure->dparameters) && is_array($structure->dparameters)) {
            $sets[] = $structure->dparameters;
        }

        return $sets;
    }

    /**
     * @param  array<int, object>  $parameters
     */
    private function charsetFromParameters(array $parameters): ?string
    {
        foreach ($parameters as $parameter) {
            $attribute = strtoupper((string) ($parameter->attribute ?? ''));

            if ($attribute !== 'CHARSET') {
                continue;
            }

            $value = trim((string) ($parameter->value ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidateCharsets(?string $charset): array
    {
        $candidates = [];

        if (is_string($charset) && trim($charset) !== '') {
            $candidates[] = trim($charset);
        }

        foreach (self::FallbackCharsets as $fallbackCharset) {
            $candidates[] = $fallbackCharset;
        }

        return array_values(array_unique($candidates));
    }

    private function convert(string $body, string $charset): ?string
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);

            if (is_string($converted) && $this->isValidUtf8($converted)) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $body);

            if (is_string($converted) && $converted !== '' && $this->isValidUtf8($converted)) {
                return $converted;
            }
        }

        return null;
    }

    private function isValidUtf8(string $value): bool
    {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($value, 'UTF-8');
        }

        return (bool) preg_match('//u', $value);
    }

    private function stripInvalidUtf8Sequences(string $value): string
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

            if (is_string($converted)) {
                return $converted;
            }
        }

        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }
}
