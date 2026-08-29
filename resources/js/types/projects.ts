export type WorkflowMode = 'drawing' | 'modelDrawing' | 'specification'

export interface ProjectCard {
    id: string
    title: string
    type_label: string
    updated_human: string
    /** `projects.image` route url, or null → fall back to a placeholder asset. */
    image_url: string | null
    folder_id: string | null
    workflow: WorkflowMode
}

export interface Folder {
    id: string
    name: string
}
