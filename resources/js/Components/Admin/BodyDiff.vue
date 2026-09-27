<script setup lang="ts">
/**
 * A side-by-side line diff of two article bodies.
 *
 * The shape comes straight from App\Services\TextDiff, which does the aligning
 * server-side: the words that changed inside a rewritten line are already
 * marked, so this component only has to colour what it is handed. Doing it here
 * would mean shipping a diff algorithm to the browser to re-derive something
 * the server already knows.
 *
 * Two columns on a wide screen, stacked on a narrow one. Prose lines are long,
 * and two 40-character columns is worse than reading the old and the new one
 * after the other.
 */

export interface DiffPart {
    text: string
    changed: boolean
}

export interface DiffRow {
    type: 'keep' | 'add' | 'remove' | 'change'
    leftNumber: number | null
    rightNumber: number | null
    left: string | null
    right: string | null
    leftParts: DiffPart[] | null
    rightParts: DiffPart[] | null
}

export interface DiffHunk {
    leftStart: number
    rightStart: number
    rows: DiffRow[]
}

export interface BodyDiffData {
    identical: boolean
    truncated: boolean
    added: number
    removed: number
    hunks: DiffHunk[]
}

defineProps<{ diff: BodyDiffData }>()

/** Tailwind cannot see into a ternary, so the classes are spelled out. */
const cell =
    'whitespace-pre-wrap break-words px-3 py-1.5 font-mono text-[12.5px] leading-relaxed align-top'

function sideClass(type: DiffRow['type'], side: 'left' | 'right'): string {
    if (type === 'keep') {
        return 'text-base-content/45'
    }

    if (type === 'remove' || (type === 'change' && side === 'left')) {
        return 'bg-error/10 text-base-content/80'
    }

    if (type === 'add' || (type === 'change' && side === 'right')) {
        return 'bg-success/10 text-base-content/80'
    }

    return 'text-base-content/45'
}

/** The empty half of a one-sided row: nothing there, and it should look it. */
function isBlank(row: DiffRow, side: 'left' | 'right'): boolean {
    return (side === 'left' ? row.left : row.right) === null
}
</script>

<template>
    <div>
        <p v-if="diff.identical" class="rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/60">
            The body is unchanged.
        </p>

        <template v-else>
            <div class="mb-3 flex flex-wrap items-center gap-3 text-[13px]">
                <span class="rounded-[4px] bg-success/15 px-2 py-0.5 font-medium text-base-content/70">
                    +{{ diff.added }} added
                </span>
                <span class="rounded-[4px] bg-error/15 px-2 py-0.5 font-medium text-base-content/70">
                    &minus;{{ diff.removed }} removed
                </span>
                <span v-if="diff.truncated" class="text-base-content/50">
                    Too little in common to line up &mdash; shown as a full replacement.
                </span>
            </div>

            <div class="overflow-hidden rounded-box border border-base-300">
                <div
                    v-for="(hunk, hunkIndex) in diff.hunks"
                    :key="hunkIndex"
                    :class="hunkIndex > 0 ? 'border-t border-base-300' : ''"
                >
                    <!-- The gap marker a code diff uses: this is where the
                         unchanged stretch was skipped. -->
                    <p class="bg-base-200 px-3 py-1 font-mono text-[11px] text-base-content/45">
                        @@ line {{ hunk.leftStart }} &rarr; {{ hunk.rightStart }} @@
                    </p>

                    <table class="w-full table-fixed border-collapse">
                        <colgroup>
                            <col class="w-10">
                            <col class="w-1/2">
                            <col class="w-10">
                            <col>
                        </colgroup>
                        <tbody>
                            <tr
                                v-for="(row, rowIndex) in hunk.rows"
                                :key="rowIndex"
                                class="border-t border-base-200 align-top"
                            >
                                <td class="select-none px-2 py-1.5 text-right font-mono text-[11px] text-base-content/30">
                                    {{ row.leftNumber ?? '' }}
                                </td>
                                <td
                                    :class="[cell, sideClass(row.type, 'left'), isBlank(row, 'left') ? 'bg-base-200/50' : '']"
                                >
                                    <template v-if="row.leftParts">
                                        <span
                                            v-for="(part, partIndex) in row.leftParts"
                                            :key="partIndex"
                                            :class="part.changed ? 'rounded-[2px] bg-error/30 text-base-content' : ''"
                                        >{{ part.text }}</span>
                                    </template>
                                    <template v-else>{{ row.left }}</template>
                                </td>

                                <td class="select-none border-l border-base-200 px-2 py-1.5 text-right font-mono text-[11px] text-base-content/30">
                                    {{ row.rightNumber ?? '' }}
                                </td>
                                <td
                                    :class="[cell, sideClass(row.type, 'right'), isBlank(row, 'right') ? 'bg-base-200/50' : '']"
                                >
                                    <template v-if="row.rightParts">
                                        <span
                                            v-for="(part, partIndex) in row.rightParts"
                                            :key="partIndex"
                                            :class="part.changed ? 'rounded-[2px] bg-success/30 text-base-content' : ''"
                                        >{{ part.text }}</span>
                                    </template>
                                    <template v-else>{{ row.right }}</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
