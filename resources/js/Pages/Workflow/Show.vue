<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    ArrowLeft,
    ArrowRight,
    Box,
    Check,
    CirclePlus,
    CloudUpload,
    FileImage,
    FileSpreadsheet,
    Move,
    Ruler,
    RotateCcw,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import FurnitureMeasurementTool from '@/Components/Workflow/FurnitureMeasurementTool.vue'
import TechnicalVisual from '@/Components/Workflow/TechnicalVisual.vue'

type WorkflowMode = 'drawing' | 'modelDrawing' | 'specification'

interface WorkflowCopy {
    newLabel: string
    title: string
    subtitle: string
    prompt: string
    resultTitle: string
    resultCopy: string
    steps: readonly string[]
    accept: string
    formats: string
    extensions: readonly string[]
    maxMb: number
}

interface ProjectStatusResponse {
    id: string
    status: 'queued' | 'processing' | 'succeeded' | 'failed'
    output_url: string | null
    error: string | null
    credits_available: number
}

const props = defineProps<{
    mode: WorkflowMode
    credits: number
    cost: number
    canModelDrawing: boolean
}>()

const workflowCopy: Record<WorkflowMode, WorkflowCopy> = {
    drawing: {
        newLabel: 'New drawing',
        title: 'Upload a product photo',
        subtitle: 'A single, clear view of the object works best.',
        prompt: 'Drop, paste or click to upload a product photo',
        resultTitle: 'Drawing concept ready',
        resultCopy: 'Your technical sheet structure is prepared for review and export.',
        steps: ['Upload', 'Measure', 'Review', 'Result'],
        accept: '.jpg,.jpeg,.png,.webp,.heic,.heif,image/*',
        formats: 'JPG, PNG, HEIC or WebP · up to 15 MB',
        extensions: ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'],
        maxMb: 15,
    },
    modelDrawing: {
        newLabel: 'New model drawing',
        title: 'Upload a 3D model',
        subtitle: 'Use a clean model with correctly grouped geometry.',
        prompt: 'Drop or click to upload a 3D model',
        resultTitle: 'Model drawing brief ready',
        resultCopy: 'Orthographic views and sheet settings are prepared for production.',
        steps: ['Upload', 'Views', 'Review', 'Result'],
        accept: '.skp,.3ds,.obj,.dwg',
        formats: 'SKP, 3DS, OBJ or DWG · up to 50 MB',
        extensions: ['skp', '3ds', 'obj', 'dwg'],
        maxMb: 50,
    },
    specification: {
        newLabel: 'New specification',
        title: 'Upload your project reference',
        subtitle: 'Add a drawing, model or product image as the source.',
        prompt: 'Drop, paste or click to upload a project reference',
        resultTitle: 'Specification draft ready',
        resultCopy: 'The project information is organized into a structured specification.',
        steps: ['Upload', 'Details', 'Review', 'Result'],
        accept: '.jpg,.jpeg,.png,.webp,.pdf,.skp,.3ds,.obj,.dwg,.dxf',
        formats: 'Image, PDF, SKP, 3DS, OBJ, DWG or DXF · up to 50 MB',
        extensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'skp', '3ds', 'obj', 'dwg', 'dxf'],
        maxMb: 50,
    },
}

const pageTitles: Record<WorkflowMode, string> = {
    drawing: 'Photo to Drawing',
    modelDrawing: '3D Model to Drawing',
    specification: 'Create Specification',
}

const copy = computed(() => workflowCopy[props.mode])
const pageTitle = computed(() => pageTitles[props.mode])

const inputRef = ref<HTMLInputElement | null>(null)
const file = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const stage = ref(0)
const error = ref('')
const unit = ref('mm')
const widthValue = ref('')
const heightValue = ref('')
const depthValue = ref('')
// Each mode's select must open on a value the server's closed list accepts —
// an out-of-list placeholder default 422s every untouched-default submission.
const defaultStyles: Record<WorkflowMode, string> = {
    drawing: 'clean orthographic set',
    modelDrawing: 'front side and plan views',
    specification: 'manufacturing specification',
}

const style = ref(defaultStyles[props.mode])
const projectName = ref('')
const material = ref('')

const isSubmitting = ref(false)
// Bumped by back/reset so a submission the user walked away from cannot
// force-jump the wizard when its response finally lands.
let submitSeq = 0
const showCreditsLink = ref(false)
const projectId = ref<string | null>(null)
const resultState = ref<'processing' | 'succeeded' | 'failed' | null>(null)
const outputUrl = ref<string | null>(null)
const resultError = ref('')

let pollTimer: ReturnType<typeof setInterval> | null = null

const activeStep = computed(() => Math.min(stage.value, copy.value.steps.length - 1))
const finalStage = computed(() => stage.value >= copy.value.steps.length - 1)

const setupTitle = computed(() =>
    props.mode === 'drawing'
        ? 'Select furniture & set real dimensions'
        : props.mode === 'modelDrawing'
          ? 'Choose the required drawing views'
          : 'Add the specification details'
)

const reviewLabel = computed(
    () => `${props.mode === 'modelDrawing' ? 'MODEL' : props.mode.toUpperCase()} / REVIEW`
)

const uploadedExtension = computed(() => file.value?.name.split('.').pop()?.toUpperCase())

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? ''
    )
}

function processFile(nextFile?: File) {
    if (!nextFile) return
    const extension = nextFile.name.split('.').pop()?.toLowerCase() || ''
    if (!copy.value.extensions.includes(extension)) {
        error.value = `Supported formats: ${copy.value.extensions.join(', ').toUpperCase()}.`
        return
    }
    if (nextFile.size > copy.value.maxMb * 1024 * 1024) {
        error.value = `The file is larger than ${copy.value.maxMb} MB.`
        return
    }

    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = nextFile.type.startsWith('image/') ? URL.createObjectURL(nextFile) : null
    file.value = nextFile
    error.value = ''
}

function stopPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

function reset() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    stopPolling()
    submitSeq += 1
    file.value = null
    previewUrl.value = null
    stage.value = 0
    widthValue.value = ''
    heightValue.value = ''
    depthValue.value = ''
    style.value = defaultStyles[props.mode]
    projectName.value = ''
    material.value = ''
    error.value = ''
    projectId.value = null
    resultState.value = null
    outputUrl.value = null
    resultError.value = ''
    showCreditsLink.value = false
    if (inputRef.value) inputRef.value.value = ''
}

function openFileDialog() {
    inputRef.value?.click()
}

function onInputChange(event: Event) {
    const target = event.target as HTMLInputElement
    processFile(target.files?.[0])
}

function handleDrop(event: DragEvent) {
    event.preventDefault()
    processFile(event.dataTransfer?.files[0])
}

function handlePaste(event: ClipboardEvent) {
    processFile(event.clipboardData?.files[0])
}

function handleKey(event: KeyboardEvent) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault()
        inputRef.value?.click()
    }
}

function startPolling(project: string) {
    stopPolling()
    pollTimer = setInterval(async () => {
        try {
            const response = await fetch(route('projects.status', project), {
                headers: { Accept: 'application/json' },
            })
            if (!response.ok) return
            const data = (await response.json()) as ProjectStatusResponse
            if (data.status === 'succeeded') {
                stopPolling()
                outputUrl.value = data.output_url
                resultState.value = 'succeeded'
            } else if (data.status === 'failed') {
                stopPolling()
                resultError.value = data.error ?? 'The drawing could not be generated.'
                resultState.value = 'failed'
            }
        } catch {
            // Transient network errors resolve on the next poll tick.
        }
    }, 2000)
}

async function submitWorkflow() {
    if (isSubmitting.value || !file.value) return

    isSubmitting.value = true
    const submission = ++submitSeq
    error.value = ''
    showCreditsLink.value = false

    const formData = new FormData()
    formData.append('mode', props.mode)
    formData.append('file', file.value)

    if (props.mode === 'drawing') {
        formData.append('unit', unit.value)
        formData.append('width', widthValue.value)
        formData.append('height', heightValue.value)
        if (depthValue.value) formData.append('depth', depthValue.value)
        formData.append('style', style.value)
    } else if (props.mode === 'modelDrawing') {
        formData.append('view_set', style.value)
        formData.append('model_units', unit.value)
    } else {
        formData.append('project_name', projectName.value)
        formData.append('material', material.value)
        formData.append('specification_type', style.value)
    }

    try {
        const response = await fetch(route('workflow.store'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: formData,
        })

        // The user pressed Back or reset while this was in flight — the
        // response no longer has a wizard to act on.
        if (submission !== submitSeq) return

        if (response.status === 422) {
            const data = (await response.json()) as { errors?: Record<string, string[]> }
            const firstError = data.errors ? Object.values(data.errors)[0] : undefined
            error.value = firstError?.[0] ?? 'The submitted data was invalid.'
            return
        }

        if (!response.ok) {
            const data = (await response.json().catch(() => null)) as {
                error?: string
                message?: string
            } | null
            error.value = data?.message ?? 'Something went wrong. Please try again.'
            showCreditsLink.value = data?.error === 'insufficient_credits'
            return
        }

        const data = (await response.json()) as { project: string }
        projectId.value = data.project
        outputUrl.value = null
        resultError.value = ''
        resultState.value = 'processing'
        stage.value = copy.value.steps.length - 1
        startPolling(data.project)
    } catch {
        error.value = 'Something went wrong. Please try again.'
    } finally {
        isSubmitting.value = false
    }
}

function handleContinue() {
    if (stage.value === 0 && !file.value) {
        error.value = 'Choose a file before continuing.'
        return
    }
    if (
        stage.value === 1 &&
        props.mode === 'drawing' &&
        (!widthValue.value ||
            Number(widthValue.value) <= 0 ||
            !heightValue.value ||
            Number(heightValue.value) <= 0)
    ) {
        error.value = 'Add valid width and height measurements.'
        return
    }
    if (stage.value === 1 && props.mode === 'specification' && !projectName.value.trim()) {
        error.value = 'Add a project or product name.'
        return
    }
    if (stage.value === 2) {
        void submitWorkflow()
        return
    }
    error.value = ''
    stage.value = stage.value + 1
}

function handleBack() {
    submitSeq += 1
    error.value = ''
    showCreditsLink.value = false
    stage.value = stage.value - 1
}

onBeforeUnmount(() => {
    stopPolling()
})
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="workflow-page" @paste="handlePaste">
            <div class="workflow-toolbar">
                <button class="text-button new-workflow" type="button" @click="reset">
                    <CirclePlus :size="18" aria-hidden="true" />
                    {{ copy.newLabel }}
                </button>
                <ol class="workflow-steps" aria-label="Progress">
                    <li
                        v-for="(step, index) in copy.steps"
                        :key="step"
                        :class="
                            index === activeStep
                                ? 'is-active'
                                : index < activeStep
                                  ? 'is-complete'
                                  : ''
                        "
                        :aria-current="index === activeStep ? 'step' : undefined"
                    >
                        <span>
                            <Check v-if="index < activeStep" :size="13" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        {{ step }}
                    </li>
                </ol>
            </div>

            <section class="workflow-card" aria-live="polite">
                <template v-if="stage === 0">
                    <div class="workflow-heading">
                        <span class="eyebrow">Step 01 · Source</span>
                        <h1>{{ copy.title }}</h1>
                        <p>{{ copy.subtitle }}</p>
                    </div>
                    <div
                        :class="['upload-dropzone', { 'has-file': file }]"
                        role="button"
                        tabindex="0"
                        :aria-label="copy.prompt"
                        @click="openFileDialog"
                        @keydown="handleKey"
                        @dragover.prevent
                        @drop="handleDrop"
                    >
                        <input
                            ref="inputRef"
                            type="file"
                            :accept="copy.accept"
                            hidden
                            @change="onInputChange"
                        />
                        <img v-if="previewUrl" :src="previewUrl" alt="Selected upload preview" />
                        <div v-else-if="file" class="uploaded-file-card" aria-hidden="true">
                            <Box :size="52" />
                            <span>{{ uploadedExtension }}</span>
                        </div>
                        <div v-else class="upload-cards" aria-hidden="true">
                            <span></span>
                            <span>
                                <Box v-if="mode === 'modelDrawing'" :size="34" />
                                <FileSpreadsheet v-else-if="mode === 'specification'" :size="34" />
                                <FileImage v-else :size="34" />
                            </span>
                            <span></span>
                        </div>
                        <CloudUpload :size="34" aria-hidden="true" />
                        <strong>{{ file ? file.name : copy.prompt }}</strong>
                        <small>{{
                            file
                                ? `${(file.size / 1024 / 1024).toFixed(2)} MB · click to replace`
                                : copy.formats
                        }}</small>
                    </div>
                </template>

                <template v-if="stage === 1">
                    <div class="workflow-heading">
                        <span class="eyebrow">Step 02 · {{ copy.steps[1] }}</span>
                        <h1>{{ setupTitle }}</h1>
                        <p>
                            {{
                                mode === 'drawing'
                                    ? 'Frame the furniture item and enter the measurements you know in the real world.'
                                    : 'These choices form the technical brief for the next production step.'
                            }}
                        </p>
                    </div>
                    <div :class="['setup-grid', { 'measurement-grid': mode === 'drawing' }]">
                        <FurnitureMeasurementTool
                            v-if="mode === 'drawing' && previewUrl"
                            :image-url="previewUrl"
                            :width-value="widthValue"
                            :height-value="heightValue"
                            :unit="unit"
                        />
                        <div v-else class="setup-preview">
                            <img v-if="previewUrl" :src="previewUrl" alt="Uploaded source" />
                            <div v-else class="setup-file-preview">
                                <Box :size="58" aria-hidden="true" />
                                <strong>{{ file?.name }}</strong>
                            </div>
                        </div>
                        <div class="setup-form">
                            <template v-if="mode === 'drawing'">
                                <div class="selection-guidance">
                                    <span><Move :size="18" aria-hidden="true" /></span>
                                    <div>
                                        <strong>Outline one furniture item</strong>
                                        <p>
                                            Choose Lasso and draw continuously around the furniture.
                                            The contour closes when you release.
                                        </p>
                                    </div>
                                </div>
                                <div class="measurement-fields">
                                    <div class="form-field">
                                        <label for="furniture-width">Width</label>
                                        <div class="dimension-input compact-dimension-input">
                                            <Ruler :size="17" aria-hidden="true" />
                                            <input
                                                id="furniture-width"
                                                :value="widthValue"
                                                type="number"
                                                min="0.1"
                                                step="0.1"
                                                placeholder="2000"
                                                inputmode="decimal"
                                                @input="
                                                    widthValue = ($event.target as HTMLInputElement)
                                                        .value
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="form-field">
                                        <label for="furniture-height">Height</label>
                                        <div class="dimension-input compact-dimension-input">
                                            <Ruler :size="17" aria-hidden="true" />
                                            <input
                                                id="furniture-height"
                                                :value="heightValue"
                                                type="number"
                                                min="0.1"
                                                step="0.1"
                                                placeholder="850"
                                                inputmode="decimal"
                                                @input="
                                                    heightValue = (
                                                        $event.target as HTMLInputElement
                                                    ).value
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="furniture-depth">Depth <span>optional</span></label>
                                    <div class="dimension-input compact-dimension-input">
                                        <Ruler :size="18" aria-hidden="true" />
                                        <input
                                            id="furniture-depth"
                                            :value="depthValue"
                                            type="number"
                                            min="0.1"
                                            step="0.1"
                                            placeholder="450"
                                            inputmode="decimal"
                                            @input="
                                                depthValue = ($event.target as HTMLInputElement)
                                                    .value
                                            "
                                        />
                                    </div>
                                </div>
                                <div class="form-field">
                                    <label for="measurement-unit">Measurement unit</label>
                                    <select id="measurement-unit" v-model="unit">
                                        <option value="mm">Millimetres (mm)</option>
                                        <option value="cm">Centimetres (cm)</option>
                                        <option value="in">Inches (in)</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="drawing-style">Drawing standard</label>
                                    <select id="drawing-style" v-model="style">
                                        <option value="clean orthographic set">
                                            Clean orthographic set
                                        </option>
                                        <option value="fabrication sheet">Fabrication sheet</option>
                                        <option value="assembly overview">Assembly overview</option>
                                    </select>
                                </div>
                            </template>
                            <template v-else-if="mode === 'modelDrawing'">
                                <div class="form-field">
                                    <label for="view-set">Drawing view set</label>
                                    <select id="view-set" v-model="style">
                                        <option value="front side and plan views">
                                            Front, side and plan views
                                        </option>
                                        <option value="orthographic and isometric views">
                                            Orthographic + isometric
                                        </option>
                                        <option value="assembly and detail views">
                                            Assembly + detail views
                                        </option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="model-units">Model units</label>
                                    <select id="model-units" v-model="unit">
                                        <option value="mm">Millimetres</option>
                                        <option value="cm">Centimetres</option>
                                        <option value="in">Inches</option>
                                    </select>
                                </div>
                            </template>
                            <template v-else>
                                <div class="form-field">
                                    <label for="project-name">Project or product name</label>
                                    <input
                                        id="project-name"
                                        v-model="projectName"
                                        placeholder="e.g. Living room shelving"
                                    />
                                </div>
                                <div class="form-field">
                                    <label for="material">Primary material</label>
                                    <input
                                        id="material"
                                        v-model="material"
                                        placeholder="e.g. Oak veneer, 30 mm"
                                    />
                                </div>
                                <div class="form-field">
                                    <label for="specification-type">Specification type</label>
                                    <select id="specification-type" v-model="style">
                                        <option value="manufacturing specification">
                                            Manufacturing specification
                                        </option>
                                        <option value="materials and finishes schedule">
                                            Materials and finishes schedule
                                        </option>
                                        <option value="project handover document">
                                            Project handover document
                                        </option>
                                    </select>
                                </div>
                            </template>
                            <div class="setup-note">
                                <strong>Secure processing</strong>
                                <p>
                                    Files upload over an encrypted connection and stay private to
                                    your workspace.
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-if="stage > 1 && !finalStage">
                    <div class="workflow-heading">
                        <span class="eyebrow">Step 03 · Review</span>
                        <h1>Review the prepared brief</h1>
                        <p>Confirm the source and output intent before the result stage.</p>
                    </div>
                    <div class="review-panel">
                        <TechnicalVisual :label="reviewLabel" />
                        <div>
                            <strong>Source</strong>
                            <p>{{ file?.name }}</p>
                            <strong>Output direction</strong>
                            <p>{{ style }}</p>
                            <template v-if="mode === 'drawing'">
                                <strong>Furniture dimensions</strong>
                                <p>
                                    {{ widthValue }} × {{ heightValue
                                    }}{{ depthValue ? ` × ${depthValue}` : '' }}
                                    {{ unit }}
                                </p>
                            </template>
                            <template v-if="mode === 'modelDrawing'">
                                <strong>Model units</strong>
                                <p>{{ unit }}</p>
                            </template>
                            <template v-if="mode === 'specification'">
                                <strong>Project</strong>
                                <p>{{ projectName }}</p>
                                <strong>Material</strong>
                                <p>{{ material || 'Not specified' }}</p>
                            </template>
                        </div>
                    </div>
                </template>

                <div v-if="finalStage" class="workflow-result">
                    <template v-if="resultState === 'processing'">
                        <span class="eyebrow">Preparing your result</span>
                        <h1>Preparing your drawing…</h1>
                        <div class="workflow-processing">
                            <TechnicalVisual label="CARPDRAW / READY" />
                        </div>
                    </template>
                    <template v-else-if="resultState === 'failed'">
                        <span class="eyebrow">Processing failed</span>
                        <h1>Something went wrong</h1>
                        <p class="workflow-error error-text" role="alert">{{ resultError }}</p>
                        <div class="result-actions">
                            <button class="secondary-button" type="button" @click="reset">
                                <RotateCcw :size="16" aria-hidden="true" />Start another
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="result-check"><Check :size="28" aria-hidden="true" /></div>
                        <span class="eyebrow">Preview complete</span>
                        <h1>{{ copy.resultTitle }}</h1>
                        <p>{{ copy.resultCopy }}</p>
                        <img
                            v-if="outputUrl"
                            class="result-image"
                            :src="outputUrl"
                            alt="Generated technical drawing"
                        />
                        <TechnicalVisual v-else label="CARPDRAW / READY" />
                        <div class="result-actions">
                            <button class="secondary-button" type="button" @click="reset">
                                <RotateCcw :size="16" aria-hidden="true" />Start another
                            </button>
                            <Link class="primary-button" :href="route('projects.index')"
                                >Save to projects<ArrowRight :size="16" aria-hidden="true"
                            /></Link>
                        </div>
                    </template>
                </div>

                <p v-if="error" class="workflow-error error-text" role="alert">{{ error }}</p>
                <Link v-if="showCreditsLink" class="primary-button" :href="route('billing.index')"
                    >Get more credits</Link
                >
                <div v-if="!finalStage" class="workflow-actions">
                    <button
                        v-if="stage > 0"
                        class="secondary-button"
                        type="button"
                        @click="handleBack"
                    >
                        <ArrowLeft :size="16" aria-hidden="true" />Back
                    </button>
                    <button
                        class="primary-button"
                        type="button"
                        :disabled="stage === 0 && !file"
                        @click="handleContinue"
                    >
                        Continue<ArrowRight :size="16" aria-hidden="true" />
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
