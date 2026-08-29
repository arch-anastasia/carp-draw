import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FurnitureMeasurementTool from '@/Components/Workflow/FurnitureMeasurementTool.vue'

const defaultPoints = [
    [18, 25],
    [29, 18],
    [58, 16],
    [78, 23],
    [86, 41],
    [82, 67],
    [68, 79],
    [39, 83],
    [20, 73],
    [13, 50],
]

const defaultPointList = defaultPoints.map(([x, y]) => `${x},${y}`).join(' ')

function mountTool(overrides: Partial<Record<'widthValue' | 'heightValue' | 'unit', string>> = {}) {
    return mount(FurnitureMeasurementTool, {
        props: {
            imageUrl: 'blob:preview',
            widthValue: '',
            heightValue: '',
            unit: 'mm',
            ...overrides,
        },
    })
}

function toolButton(wrapper: ReturnType<typeof mountTool>, label: string) {
    const button = wrapper.findAll('button.lasso-tool-button').find((candidate) => {
        return candidate.text().includes(label)
    })

    expect(button).toBeDefined()
    return button!
}

describe('FurnitureMeasurementTool', () => {
    it('renders the default decagon as a closed polygon', () => {
        const wrapper = mountTool()

        const polygon = wrapper.find('polygon.lasso-selection')

        expect(polygon.exists()).toBe(true)
        expect(polygon.attributes('points')).toBe(defaultPointList)
        expect(wrapper.find('polyline.lasso-selection.is-drawing').exists()).toBe(false)
    })

    it('starts in lasso mode with the matching canvas class and status copy', () => {
        const wrapper = mountTool()

        expect(wrapper.classes()).toContain('is-lasso')
        expect(toolButton(wrapper, 'Lasso').attributes('aria-pressed')).toBe('true')
        expect(toolButton(wrapper, 'Move').attributes('aria-pressed')).toBe('false')

        const status = wrapper.find('.lasso-status')
        expect(status.attributes('aria-live')).toBe('polite')
        expect(status.text()).toBe('Draw a freehand contour around one item')
    })

    it('toggles aria-pressed and the canvas mode class when Move is chosen', async () => {
        const wrapper = mountTool()

        await toolButton(wrapper, 'Move').trigger('click')

        expect(wrapper.classes()).toContain('is-move')
        expect(toolButton(wrapper, 'Move').attributes('aria-pressed')).toBe('true')
        expect(toolButton(wrapper, 'Move').classes()).toContain('is-active')
        expect(toolButton(wrapper, 'Lasso').attributes('aria-pressed')).toBe('false')
        expect(wrapper.find('.lasso-status').text()).toBe('Drag inside the contour to move it')
    })

    it('positions the dimension guides from the polygon bounds', () => {
        const wrapper = mountTool()

        const widthGuide = wrapper.find('.dimension-guide-width')
        const heightGuide = wrapper.find('.dimension-guide-height')

        // Bounds of the decagon: x 13-86, y 16-83.
        expect(widthGuide.attributes('style')).toContain('left: 13%')
        expect(widthGuide.attributes('style')).toContain('top: 8%')
        expect(widthGuide.attributes('style')).toContain('width: 73%')
        expect(heightGuide.attributes('style')).toContain('left: 6%')
        expect(heightGuide.attributes('style')).toContain('top: 16%')
        expect(heightGuide.attributes('style')).toContain('height: 67%')
    })

    it('falls back to axis labels when no measurements are typed', () => {
        const wrapper = mountTool()

        expect(wrapper.find('.dimension-guide-width strong').text()).toBe('Width · mm')
        expect(wrapper.find('.dimension-guide-height strong').text()).toBe('Height · mm')
    })

    it('shows the typed measurements with their unit in the guides', () => {
        const wrapper = mountTool({ widthValue: '2000', heightValue: '850', unit: 'cm' })

        expect(wrapper.find('.dimension-guide-width strong').text()).toBe('2000 cm')
        expect(wrapper.find('.dimension-guide-height strong').text()).toBe('850 cm')
    })

    it('nudges the contour with arrow keys in move mode', async () => {
        const wrapper = mountTool()

        await toolButton(wrapper, 'Move').trigger('click')
        await wrapper.trigger('keydown', { key: 'ArrowRight' })

        const nudged = defaultPoints.map(([x, y]) => `${x + 1},${y}`).join(' ')
        expect(wrapper.find('polygon.lasso-selection').attributes('points')).toBe(nudged)
    })

    it('doubles the nudge distance with Shift held', async () => {
        const wrapper = mountTool()

        await toolButton(wrapper, 'Move').trigger('click')
        await wrapper.trigger('keydown', { key: 'ArrowDown', shiftKey: true })

        const nudged = defaultPoints.map(([x, y]) => `${x},${y + 2}`).join(' ')
        expect(wrapper.find('polygon.lasso-selection').attributes('points')).toBe(nudged)
    })

    it('ignores arrow keys while in lasso mode', async () => {
        const wrapper = mountTool()

        await wrapper.trigger('keydown', { key: 'ArrowRight' })

        expect(wrapper.find('polygon.lasso-selection').attributes('points')).toBe(defaultPointList)
    })

    it('reset restores the default decagon and returns to lasso mode', async () => {
        const wrapper = mountTool()

        await toolButton(wrapper, 'Move').trigger('click')
        await wrapper.trigger('keydown', { key: 'ArrowRight' })
        expect(wrapper.find('polygon.lasso-selection').attributes('points')).not.toBe(
            defaultPointList
        )

        await toolButton(wrapper, 'Reset').trigger('click')

        expect(wrapper.find('polygon.lasso-selection').attributes('points')).toBe(defaultPointList)
        expect(wrapper.classes()).toContain('is-lasso')
        expect(toolButton(wrapper, 'Lasso').attributes('aria-pressed')).toBe('true')
        expect(toolButton(wrapper, 'Move').attributes('aria-pressed')).toBe('false')
    })

    it('exposes the canvas as a labelled application region', () => {
        const wrapper = mountTool()

        expect(wrapper.attributes('role')).toBe('application')
        expect(wrapper.attributes('tabindex')).toBe('0')
        expect(wrapper.attributes('aria-label')).toBe(
            'Furniture lasso selection. Choose Lasso and draw around the furniture. Choose Move to reposition the closed contour.'
        )
        expect(wrapper.find('img').attributes('alt')).toBe('Uploaded furniture reference')
    })
})
