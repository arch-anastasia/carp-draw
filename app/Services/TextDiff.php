<?php

namespace App\Services;

class TextDiff
{
    private const MAX_CELLS = 250_000;

    /**
     * @return array{
     *     identical: bool,
     *     truncated: bool,
     *     added: int,
     *     removed: int,
     *     hunks: array<int, array{
     *         leftStart: int,
     *         rightStart: int,
     *         rows: array<int, array{
     *             type: string,
     *             leftNumber: int|null,
     *             rightNumber: int|null,
     *             left: string|null,
     *             right: string|null,
     *             leftParts: array<int, array{text: string, changed: bool}>|null,
     *             rightParts: array<int, array{text: string, changed: bool}>|null
     *         }>
     *     }>
     * }
     */
    public function sideBySide(string $before, string $after, int $context = 3): array
    {
        $left = $this->lines($before);
        $right = $this->lines($after);

        if ($left === $right) {
            return ['identical' => true, 'truncated' => false, 'added' => 0, 'removed' => 0, 'hunks' => []];
        }

        [$operations, $truncated] = $this->operations($left, $right);

        $rows = $this->rows($operations, $left, $right);

        return [
            'identical' => false,
            'truncated' => $truncated,
            'added' => count(array_filter($rows, fn (array $row): bool => $row['right'] !== null && $row['type'] !== 'keep')),
            'removed' => count(array_filter($rows, fn (array $row): bool => $row['left'] !== null && $row['type'] !== 'keep')),
            'hunks' => $this->hunks($rows, $context),
        ];
    }

    /**
     * Normalised to \n and split. A body edited in a browser textarea comes
     * back with \r\n on some platforms, and a diff that reports every line as
     * changed because of an invisible byte is worse than no diff at all.
     *
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * The edit script, as a flat list of [type, leftIndex, rightIndex].
     *
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array{0: array<int, array{0: string, 1: int|null, 2: int|null}>, 1: bool}
     */
    private function operations(array $left, array $right): array
    {
        $operations = [];

        /*
         * Common prefix and suffix first. These are the lines an editor did
         * not touch, they are usually most of the article, and aligning them
         * with a quadratic algorithm is work whose answer is already known.
         */
        $prefix = 0;
        $leftCount = count($left);
        $rightCount = count($right);

        while ($prefix < $leftCount && $prefix < $rightCount && $left[$prefix] === $right[$prefix]) {
            $operations[] = ['keep', $prefix, $prefix];
            $prefix++;
        }

        $suffix = 0;

        while ($suffix < $leftCount - $prefix
            && $suffix < $rightCount - $prefix
            && $left[$leftCount - 1 - $suffix] === $right[$rightCount - 1 - $suffix]) {
            $suffix++;
        }

        $leftMiddle = array_slice($left, $prefix, $leftCount - $prefix - $suffix);
        $rightMiddle = array_slice($right, $prefix, $rightCount - $prefix - $suffix);

        $truncated = count($leftMiddle) * count($rightMiddle) > self::MAX_CELLS;

        $middle = $truncated
            ? $this->wholesale($leftMiddle, $rightMiddle)
            : $this->longestCommonSubsequence($leftMiddle, $rightMiddle);

        foreach ($middle as $operation) {
            $operations[] = [
                $operation[0],
                $operation[1] === null ? null : $operation[1] + $prefix,
                $operation[2] === null ? null : $operation[2] + $prefix,
            ];
        }

        for ($index = 0; $index < $suffix; $index++) {
            $operations[] = ['keep', $leftCount - $suffix + $index, $rightCount - $suffix + $index];
        }

        return [$operations, $truncated];
    }

    /**
     * Everything on the left removed, everything on the right added. The
     * answer when two texts are too far apart to be worth aligning.
     *
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array<int, array{0: string, 1: int|null, 2: int|null}>
     */
    private function wholesale(array $left, array $right): array
    {
        $operations = [];

        foreach (array_keys($left) as $index) {
            $operations[] = ['remove', $index, null];
        }

        foreach (array_keys($right) as $index) {
            $operations[] = ['add', null, $index];
        }

        return $operations;
    }

    /**
     * Classic LCS: fill the table from the bottom right, then walk it from the
     * top left, preferring a removal on a tie so a replaced block reads as
     * "this became that" rather than "that appeared, this vanished".
     *
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array<int, array{0: string, 1: int|null, 2: int|null}>
     */
    private function longestCommonSubsequence(array $left, array $right): array
    {
        $leftCount = count($left);
        $rightCount = count($right);

        $table = array_fill(0, $leftCount + 1, array_fill(0, $rightCount + 1, 0));

        for ($i = $leftCount - 1; $i >= 0; $i--) {
            for ($j = $rightCount - 1; $j >= 0; $j--) {
                $table[$i][$j] = $left[$i] === $right[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;

        while ($i < $leftCount && $j < $rightCount) {
            if ($left[$i] === $right[$j]) {
                $operations[] = ['keep', $i, $j];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $operations[] = ['remove', $i, null];
                $i++;
            } else {
                $operations[] = ['add', null, $j];
                $j++;
            }
        }

        while ($i < $leftCount) {
            $operations[] = ['remove', $i, null];
            $i++;
        }

        while ($j < $rightCount) {
            $operations[] = ['add', null, $j];
            $j++;
        }

        return $operations;
    }

    /**
     * Turn the edit script into printable rows.
     *
     * Consecutive removals and additions are zipped together, so a rewritten
     * paragraph occupies one row with the old wording on the left and the new
     * on the right rather than two rows the reader has to pair up by eye.
     * Whichever run is longer leaves one-sided rows behind, which is exactly
     * what "three paragraphs became five" should look like.
     *
     * @param  array<int, array{0: string, 1: int|null, 2: int|null}>  $operations
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array<int, array{type: string, leftNumber: int|null, rightNumber: int|null, left: string|null, right: string|null, leftParts: array<int, array{text: string, changed: bool}>|null, rightParts: array<int, array{text: string, changed: bool}>|null}>
     */
    private function rows(array $operations, array $left, array $right): array
    {
        $rows = [];

        /** @var array<int, int> $removals */
        $removals = [];

        /** @var array<int, int> $additions */
        $additions = [];

        foreach ($operations as [$type, $leftIndex, $rightIndex]) {
            if ($type === 'remove' && $leftIndex !== null) {
                $removals[] = $leftIndex;

                continue;
            }

            if ($type === 'add' && $rightIndex !== null) {
                $additions[] = $rightIndex;

                continue;
            }

            array_push($rows, ...$this->zip($removals, $additions, $left, $right));
            $removals = [];
            $additions = [];

            $rows[] = [
                'type' => 'keep',
                'leftNumber' => $leftIndex + 1,
                'rightNumber' => $rightIndex + 1,
                'left' => $left[$leftIndex],
                'right' => $right[$rightIndex],
                'leftParts' => null,
                'rightParts' => null,
            ];
        }

        array_push($rows, ...$this->zip($removals, $additions, $left, $right));

        return $rows;
    }

    /**
     * Pair a run of removals with the run of additions that replaced it.
     *
     * Whichever run is longer leaves one-sided rows behind, which is exactly
     * what "three paragraphs became five" should look like.
     *
     * @param  array<int, int>  $removals
     * @param  array<int, int>  $additions
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array<int, array{type: string, leftNumber: int|null, rightNumber: int|null, left: string|null, right: string|null, leftParts: array<int, array{text: string, changed: bool}>|null, rightParts: array<int, array{text: string, changed: bool}>|null}>
     */
    private function zip(array $removals, array $additions, array $left, array $right): array
    {
        $rows = [];
        $pairs = max(count($removals), count($additions));

        for ($index = 0; $index < $pairs; $index++) {
            $leftIndex = $removals[$index] ?? null;
            $rightIndex = $additions[$index] ?? null;

            $leftText = $leftIndex === null ? null : $left[$leftIndex];
            $rightText = $rightIndex === null ? null : $right[$rightIndex];

            /* Both sides present means a rewrite, which earns word marks. */
            $parts = $leftText !== null && $rightText !== null
                ? $this->words($leftText, $rightText)
                : ['left' => null, 'right' => null];

            $rows[] = [
                'type' => match (true) {
                    $leftText !== null && $rightText !== null => 'change',
                    $leftText !== null => 'remove',
                    default => 'add',
                },
                'leftNumber' => $leftIndex === null ? null : $leftIndex + 1,
                'rightNumber' => $rightIndex === null ? null : $rightIndex + 1,
                'left' => $leftText,
                'right' => $rightText,
                'leftParts' => $parts['left'],
                'rightParts' => $parts['right'],
            ];
        }

        return $rows;
    }

    /**
     * Mark what actually changed inside a rewritten line.
     *
     * The same LCS over words rather than lines. Whitespace is captured as its
     * own token and kept, so the marked-up line still reads as the sentence it
     * is rather than as a list of words.
     *
     * @return array{left: array<int, array{text: string, changed: bool}>, right: array<int, array{text: string, changed: bool}>}
     */
    private function words(string $left, string $right): array
    {
        $leftWords = (array) preg_split('/(\s+)/u', $left, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $rightWords = (array) preg_split('/(\s+)/u', $right, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        /* A pathological line is not worth a table; mark the whole of it. */
        if (count($leftWords) * count($rightWords) > self::MAX_CELLS) {
            return [
                'left' => [['text' => $left, 'changed' => true]],
                'right' => [['text' => $right, 'changed' => true]],
            ];
        }

        $operations = $this->longestCommonSubsequence(
            array_map('strval', $leftWords),
            array_map('strval', $rightWords),
        );

        $leftParts = [];
        $rightParts = [];

        foreach ($operations as [$type, $leftIndex, $rightIndex]) {
            if ($type !== 'add' && $leftIndex !== null) {
                $this->append($leftParts, (string) $leftWords[$leftIndex], $type === 'remove');
            }

            if ($type !== 'remove' && $rightIndex !== null) {
                $this->append($rightParts, (string) $rightWords[$rightIndex], $type === 'add');
            }
        }

        return ['left' => $leftParts, 'right' => $rightParts];
    }

    /**
     * Adjacent tokens of the same kind are merged, so a run of five changed
     * words is one highlighted phrase rather than five boxes with gaps.
     *
     * @param  array<int, array{text: string, changed: bool}>  $parts
     */
    private function append(array &$parts, string $text, bool $changed): void
    {
        $last = count($parts) - 1;

        if ($last >= 0 && $parts[$last]['changed'] === $changed) {
            $parts[$last]['text'] .= $text;

            return;
        }

        $parts[] = ['text' => $text, 'changed' => $changed];
    }

    /**
     * Group rows into hunks, keeping `$context` unchanged lines either side of
     * a change and dropping the untouched stretches in between.
     *
     * @param  array<int, array{type: string, leftNumber: int|null, rightNumber: int|null, left: string|null, right: string|null, leftParts: array<int, array{text: string, changed: bool}>|null, rightParts: array<int, array{text: string, changed: bool}>|null}>  $rows
     * @return array<int, array{leftStart: int, rightStart: int, rows: array<int, array<string, mixed>>}>
     */
    private function hunks(array $rows, int $context): array
    {
        $keep = [];

        foreach ($rows as $index => $row) {
            if ($row['type'] === 'keep') {
                continue;
            }

            for ($offset = -$context; $offset <= $context; $offset++) {
                if (isset($rows[$index + $offset])) {
                    $keep[$index + $offset] = true;
                }
            }
        }

        ksort($keep);

        $hunks = [];
        $current = null;
        $previous = null;

        foreach (array_keys($keep) as $index) {
            if ($current === null || $index !== $previous + 1) {
                if ($current !== null) {
                    $hunks[] = $current;
                }

                $current = [
                    'leftStart' => $rows[$index]['leftNumber'] ?? 0,
                    'rightStart' => $rows[$index]['rightNumber'] ?? 0,
                    'rows' => [],
                ];
            }

            $current['rows'][] = $rows[$index];
            $previous = $index;
        }

        if ($current !== null) {
            $hunks[] = $current;
        }

        return $hunks;
    }
}
