<?php

namespace App\Services\Drawing\Concerns;

/**
 * Reading an answer out of a Gemini Interactions turn.
 *
 * The response is a `steps` array, and only some of those steps are the model
 * talking. Two of them are actively dangerous to read:
 *
 *  - `user_input` is OUR OWN REQUEST echoed back, selfie and all. Scanning it
 *    for an image block returns the visitor's unedited photograph as though it
 *    were the render - a picture they paid for that changes nothing.
 *  - `thought` is the model's reasoning. It is not an answer, and on a describe
 *    call it is reasoning about a photograph of a person, which must never
 *    reach a prompt applied to somebody else's face.
 *
 * Everything else is fair game, so a step type added later is read rather than
 * silently dropped.
 */
trait ReadsInteractionTurns
{
    /**
     * Every content block the MODEL produced, in order.
     *
     * @param  array<int, mixed>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function modelBlocks(array $steps): array
    {
        $blocks = [];

        foreach ($steps as $step) {
            if (! is_array($step) || in_array($step['type'] ?? null, ['user_input', 'thought'], true)) {
                continue;
            }

            foreach ((array) ($step['content'] ?? []) as $block) {
                if (is_array($block)) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }
}
