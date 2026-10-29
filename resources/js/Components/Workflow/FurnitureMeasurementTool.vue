<script setup lang="ts">
import { computed, ref } from 'vue'
import { Move, PenTool, RotateCcw } from 'lucide-vue-next'

interface Point {
    x: number
    y: number
}

type ToolMode = 'lasso' | 'move'

type Interaction = { mode: 'draw' } | { mode: 'move'; start: Point; initialPoints: Point[] }

const props = defineProps<{
    imageUrl: string
    widthValue: string
    heightValue: string
    unit: string
}>()

const defaultPoints: Point[] = [
    { x: 18, y: 25 },
    { x: 29, y: 18 },
    { x: 58, y: 16 },
    { x: 78, y: 23 },
    { x: 86, y: 41 },
    { x: 82, y: 67 },
    { x: 68, y: 79 },
    { x: 39, y: 83 },
    { x: 20, y: 73 },
    { x: 13, y: 50 },
]

const clamp = (value: number, minimum: number, maximum: number) =>
    Math.min(Math.max(value, minimum), maximum)

const getBounds = (points: Point[]) => {
    const xs = points.map((point) => point.x)
    const ys = points.map((point) => point.y)
    const minX = Math.min(...xs)
    const maxX = Math.max(...xs)
    const minY = Math.min(...ys)
    const maxY = Math.max(...ys)

    return {
        minX,
        maxX,
        minY,
        maxY,
        width: maxX - minX,
        height: maxY - minY,
    }
}

const isInsidePolygon = (point: Point, polygon: Point[]) => {
    let inside = false

    for (let index = 0, previous = polygon.length - 1; index < polygon.length; previous = index++) {
        const currentPoint = polygon[index]
        const previousPoint = polygon[previous]
        const intersects =
            currentPoint.y > point.y !== previousPoint.y > point.y &&
            point.x <
                ((previousPoint.x - currentPoint.x) * (point.y - currentPoint.y)) /
                    (previousPoint.y - currentPoint.y) +
                    currentPoint.x

        if (intersects) inside = !inside
    }

    return inside
}

const translatePoints = (points: Point[], deltaX: number, deltaY: number) => {
    const bounds = getBounds(points)
    const safeDeltaX = clamp(deltaX, -bounds.minX, 100 - bounds.maxX)
    const safeDeltaY = clamp(deltaY, -bounds.minY, 100 - bounds.maxY)

    return points.map((point) => ({
        x: point.x + safeDeltaX,
        y: point.y + safeDeltaY,
    }))
}

const canvasRef = ref<HTMLDivElement | null>(null)
const points = ref<Point[]>([...defaultPoints])
const toolMode = ref<ToolMode>('lasso')
const isDrawing = ref(false)

// Held outside reactivity on purpose: the interaction descriptor changes on
// every pointer event and nothing renders from it, so a plain variable avoids
// re-render churn during drags.
let interaction: Interaction | null = null

const bounds = computed(() => getBounds(points.value.length ? points.value : defaultPoints))
const pointList = computed(() => points.value.map((point) => `${point.x},${point.y}`).join(' '))

const widthGuideStyle = computed(() => ({
    left: `${bounds.value.minX}%`,
    top: `${Math.max(4, bounds.value.minY - 8)}%`,
    width: `${bounds.value.width}%`,
}))

const heightGuideStyle = computed(() => ({
    left: `${Math.max(4, bounds.value.minX - 7)}%`,
    top: `${bounds.value.minY}%`,
    height: `${bounds.value.height}%`,
}))

const calloutWidth = computed(() =>
    props.widthValue ? `${props.widthValue} ${props.unit}` : `Width · ${props.unit}`
)
const calloutHeight = computed(() =>
    props.heightValue ? `${props.heightValue} ${props.unit}` : `Height · ${props.unit}`
)

const statusText = computed(() =>
    isDrawing.value
        ? 'Keep drawing around the furniture'
        : toolMode.value === 'lasso'
          ? 'Draw a freehand contour around one item'
          : 'Drag inside the contour to move it'
)

function pointFromEvent(event: PointerEvent): Point {
    const canvasBounds = canvasRef.value?.getBoundingClientRect()
    if (!canvasBounds) return { x: 0, y: 0 }

    return {
        x: clamp(((event.clientX - canvasBounds.left) / canvasBounds.width) * 100, 0, 100),
        y: clamp(((event.clientY - canvasBounds.top) / canvasBounds.height) * 100, 0, 100),
    }
}

function beginInteraction(event: PointerEvent) {
    const target = event.target as HTMLElement
    if (target.closest('[data-measurement-control]')) return

    const point = pointFromEvent(event)
    if (toolMode.value === 'move' && !isInsidePolygon(point, points.value)) return

    event.preventDefault()
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)

    if (toolMode.value === 'lasso') {
        interaction = { mode: 'draw' }
        points.value = [point]
        isDrawing.value = true
        return
    }

    interaction = {
        mode: 'move',
        start: point,
        initialPoints: points.value,
    }
}

function updateInteraction(event: PointerEvent) {
    if (!interaction) return

    const point = pointFromEvent(event)

    if (interaction.mode === 'draw') {
        const previousPoint = points.value[points.value.length - 1]
        if (!previousPoint) {
            points.value = [point]
            return
        }

        const distance = Math.hypot(point.x - previousPoint.x, point.y - previousPoint.y)
        if (distance >= 0.75) points.value = [...points.value, point]
        return
    }

    points.value = translatePoints(
        interaction.initialPoints,
        point.x - interaction.start.x,
        point.y - interaction.start.y
    )
}

function endInteraction(event: PointerEvent) {
    const current = interaction
    if (!current) return

    interaction = null
    const canvas = event.currentTarget as HTMLElement
    if (canvas.hasPointerCapture(event.pointerId)) {
        canvas.releasePointerCapture(event.pointerId)
    }

    if (current.mode === 'draw') {
        isDrawing.value = false
        if (points.value.length < 4) {
            points.value = [...defaultPoints]
            return
        }
        const currentBounds = getBounds(points.value)
        if (currentBounds.width < 5 || currentBounds.height < 5) {
            points.value = [...defaultPoints]
        }
    }
}

function handleKeyboard(event: KeyboardEvent) {
    if (toolMode.value !== 'move') return
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return

    event.preventDefault()
    const movement = event.shiftKey ? 2 : 1
    const deltaX = event.key === 'ArrowLeft' ? -movement : event.key === 'ArrowRight' ? movement : 0
    const deltaY = event.key === 'ArrowUp' ? -movement : event.key === 'ArrowDown' ? movement : 0
    points.value = translatePoints(points.value, deltaX, deltaY)
}

function resetSelection() {
    points.value = [...defaultPoints]
    toolMode.value = 'lasso'
    isDrawing.value = false
}
</script>

<template>
    <div
        ref="canvasRef"
        :class="`measurement-canvas is-${toolMode}`"
        tabindex="0"
        role="application"
        aria-label="Furniture lasso selection. Choose Lasso and draw around the furniture. Choose Move to reposition the closed contour."
        @keydown="handleKeyboard"
        @pointerdown="beginInteraction"
        @pointermove="updateInteraction"
        @pointerup="endInteraction"
        @pointercancel="endInteraction"
    >
        <img :src="imageUrl" alt="Uploaded furniture reference" draggable="false" />
        <div class="measurement-canvas-shade" aria-hidden="true"></div>

        <svg
            class="lasso-overlay"
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <polyline v-if="isDrawing" class="lasso-selection is-drawing" :points="pointList" />
            <polygon v-else class="lasso-selection" :points="pointList" />
        </svg>

        <template v-if="!isDrawing">
            <div
                class="dimension-guide dimension-guide-width"
                :style="widthGuideStyle"
                aria-hidden="true"
            >
                <span class="dimension-cap"></span>
                <strong>{{ calloutWidth }}</strong>
                <span class="dimension-cap"></span>
            </div>

            <div
                class="dimension-guide dimension-guide-height"
                :style="heightGuideStyle"
                aria-hidden="true"
            >
                <span class="dimension-cap"></span>
                <strong>{{ calloutHeight }}</strong>
                <span class="dimension-cap"></span>
            </div>
        </template>

        <div class="lasso-toolbar" data-measurement-control>
            <button
                :class="['lasso-tool-button', { 'is-active': toolMode === 'lasso' }]"
                type="button"
                :aria-pressed="toolMode === 'lasso' ? 'true' : 'false'"
                @click="toolMode = 'lasso'"
            >
                <PenTool :size="15" aria-hidden="true" />
                Lasso
            </button>
            <button
                :class="['lasso-tool-button', { 'is-active': toolMode === 'move' }]"
                type="button"
                :aria-pressed="toolMode === 'move' ? 'true' : 'false'"
                @click="toolMode = 'move'"
            >
                <Move :size="15" aria-hidden="true" />
                Move
            </button>
            <button class="lasso-tool-button" type="button" @click="resetSelection">
                <RotateCcw :size="15" aria-hidden="true" />
                Reset
            </button>
        </div>

        <div class="lasso-status" data-measurement-control aria-live="polite">
            {{ statusText }}
        </div>
    </div>
</template>
