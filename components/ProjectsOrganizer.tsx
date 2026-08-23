"use client";

import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import {
  ArrowRight,
  Folder,
  FolderOpen,
  FolderPlus,
  Layers3,
} from "lucide-react";
import Link from "next/link";

type RoomFolder = {
  id: string;
  name: string;
};

type SavedProject = {
  id: string;
  title: string;
  type: string;
  updated: string;
  image: string;
  folderId: string;
  href: string;
};

const storageKey = "carpdraw-project-library-v1";

const defaultFolders: RoomFolder[] = [
  { id: "kitchen", name: "Kitchen" },
  { id: "bathroom", name: "Bathroom" },
  { id: "living-room", name: "Living Room" },
  { id: "bedroom", name: "Bedroom" },
];

const defaultProjects: SavedProject[] = [
  {
    id: "kitchen-specification",
    title: "Kitchen specification set",
    type: "Technical drawing",
    updated: "Updated today",
    image: "/examples/example-kitchen-drawing.webp",
    folderId: "kitchen",
    href: "/app/photo-to-drawing",
  },
  {
    id: "bathroom-vanity",
    title: "Bathroom vanity drawing",
    type: "Photo to drawing",
    updated: "Updated yesterday",
    image: "/examples/gallery/bathroom-walnut-drawing.webp",
    folderId: "bathroom",
    href: "/app/photo-to-drawing",
  },
  {
    id: "living-room-shelving",
    title: "Living room shelving",
    type: "Specification",
    updated: "Updated 3 days ago",
    image: "/examples/shelving-technical-sheet.webp",
    folderId: "living-room",
    href: "/app/create-specification",
  },
  {
    id: "bedroom-storage",
    title: "Bedroom storage system",
    type: "Technical drawing",
    updated: "Updated 1 week ago",
    image: "/examples/example-bunk-drawing.webp",
    folderId: "bedroom",
    href: "/app/model-to-drawing",
  },
];

export function ProjectsOrganizer() {
  const [folders, setFolders] = useState<RoomFolder[]>(defaultFolders);
  const [projects, setProjects] = useState<SavedProject[]>(defaultProjects);
  const [activeFolderId, setActiveFolderId] = useState("all");
  const [isCreatingFolder, setIsCreatingFolder] = useState(false);
  const [folderName, setFolderName] = useState("");
  const [folderError, setFolderError] = useState("");
  const hasLoadedStorage = useRef(false);

  useEffect(() => {
    let isCancelled = false;

    queueMicrotask(() => {
      if (isCancelled) return;
      const savedLibrary = window.localStorage.getItem(storageKey);
      if (savedLibrary) {
        try {
          const parsed = JSON.parse(savedLibrary) as {
            folders?: RoomFolder[];
            projects?: SavedProject[];
          };
          if (Array.isArray(parsed.folders)) setFolders(parsed.folders);
          if (Array.isArray(parsed.projects)) setProjects(parsed.projects);
        } catch {
          window.localStorage.removeItem(storageKey);
        }
      }
      hasLoadedStorage.current = true;
    });

    return () => {
      isCancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!hasLoadedStorage.current) return;
    window.localStorage.setItem(storageKey, JSON.stringify({ folders, projects }));
  }, [folders, projects]);

  const visibleProjects = useMemo(
    () =>
      activeFolderId === "all"
        ? projects
        : projects.filter((project) => project.folderId === activeFolderId),
    [activeFolderId, projects],
  );

  const activeFolderName =
    activeFolderId === "all"
      ? "All projects"
      : folders.find((folder) => folder.id === activeFolderId)?.name ?? "Projects";

  const getFolderCount = (folderId: string) =>
    folderId === "all"
      ? projects.length
      : projects.filter((project) => project.folderId === folderId).length;

  const createFolder = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const trimmedName = folderName.trim();

    if (!trimmedName) {
      setFolderError("Enter a room or folder name.");
      return;
    }

    if (folders.some((folder) => folder.name.toLowerCase() === trimmedName.toLowerCase())) {
      setFolderError("A folder with this name already exists.");
      return;
    }

    const baseId = trimmedName
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-|-$/g, "");
    const id = `${baseId || "room"}-${Date.now().toString(36)}`;

    setFolders((currentFolders) => [...currentFolders, { id, name: trimmedName }]);
    setActiveFolderId(id);
    setFolderName("");
    setFolderError("");
    setIsCreatingFolder(false);
  };

  const moveProject = (projectId: string, folderId: string) => {
    setProjects((currentProjects) =>
      currentProjects.map((project) =>
        project.id === projectId ? { ...project, folderId } : project,
      ),
    );
  };

  return (
    <div className="projects-organizer">
      <aside className="room-folders" aria-label="Project folders">
        <div className="room-folders-heading">
          <div>
            <span className="eyebrow">Rooms</span>
            <strong>Project folders</strong>
          </div>
          <button
            type="button"
            aria-label="Create a new room folder"
            aria-expanded={isCreatingFolder}
            onClick={() => {
              setIsCreatingFolder((current) => !current);
              setFolderError("");
            }}
          >
            <FolderPlus size={18} aria-hidden="true" />
          </button>
        </div>

        {isCreatingFolder && (
          <form className="new-folder-form" onSubmit={createFolder}>
            <label htmlFor="new-room-folder">Room or folder name</label>
            <div>
              <input
                id="new-room-folder"
                value={folderName}
                onChange={(event) => {
                  setFolderName(event.target.value);
                  setFolderError("");
                }}
                placeholder="e.g. Home office"
                autoFocus
              />
              <button type="submit" disabled={!folderName.trim()}>
                Add
              </button>
            </div>
            {folderError && <p role="alert">{folderError}</p>}
          </form>
        )}

        <nav className="folder-list" aria-label="Filter projects by room">
          <button
            className={activeFolderId === "all" ? "is-active" : ""}
            type="button"
            aria-pressed={activeFolderId === "all"}
            onClick={() => setActiveFolderId("all")}
          >
            <span>
              <Layers3 size={17} aria-hidden="true" />
              All projects
            </span>
            <small>{getFolderCount("all")}</small>
          </button>
          {folders.map((folder) => {
            const isActive = activeFolderId === folder.id;
            const FolderIcon = isActive ? FolderOpen : Folder;

            return (
              <button
                className={isActive ? "is-active" : ""}
                type="button"
                aria-pressed={isActive}
                onClick={() => setActiveFolderId(folder.id)}
                key={folder.id}
              >
                <span>
                  <FolderIcon size={17} aria-hidden="true" />
                  {folder.name}
                </span>
                <small>{getFolderCount(folder.id)}</small>
              </button>
            );
          })}
        </nav>
      </aside>

      <section className="project-folder-content" aria-labelledby="active-folder-title">
        <div className="project-folder-heading">
          <div>
            <span className="eyebrow">Current folder</span>
            <h2 id="active-folder-title">{activeFolderName}</h2>
          </div>
          <span>{visibleProjects.length} {visibleProjects.length === 1 ? "project" : "projects"}</span>
        </div>

        {visibleProjects.length ? (
          <div className="projects-grid">
            {visibleProjects.map((project) => (
              <article className="project-library-card" key={project.id}>
                <div className="project-library-image">
                  <img src={project.image} alt="" />
                </div>
                <div className="project-library-copy">
                  <div>
                    <h3>{project.title}</h3>
                    <p>{project.type} · {project.updated}</p>
                  </div>
                  <label>
                    <span>Room</span>
                    <select
                      aria-label={`Move ${project.title} to folder`}
                      value={project.folderId}
                      onChange={(event) => moveProject(project.id, event.target.value)}
                    >
                      {folders.map((folder) => (
                        <option value={folder.id} key={folder.id}>
                          {folder.name}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
                <Link href={project.href}>
                  Open project
                  <ArrowRight size={15} aria-hidden="true" />
                </Link>
              </article>
            ))}
          </div>
        ) : (
          <div className="empty-folder-state">
            <FolderOpen size={32} aria-hidden="true" />
            <h3>This folder is empty</h3>
            <p>Move an existing project here, or start a new drawing for this room.</p>
            <Link className="primary-button" href="/app/photo-to-drawing">
              Create project
              <ArrowRight size={16} aria-hidden="true" />
            </Link>
          </div>
        )}
      </section>
    </div>
  );
}
