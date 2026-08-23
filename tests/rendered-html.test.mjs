import assert from "node:assert/strict";
import { access, readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);

async function render(path = "/") {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);

  return worker.fetch(
    new Request(`http://localhost${path}`, { headers: { accept: "text/html" } }),
    { ASSETS: { fetch: async () => new Response("Not found", { status: 404 }) } },
    { waitUntil() {}, passThroughOnException() {} },
  );
}

test("server-renders the CarpDraw landing page", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);

  const html = await response.text();
  assert.match(html, /CarpDraw AI/i);
  assert.match(html, /From a reference to a clear technical drawing/i);
  assert.match(html, /3D Model to Drawing/i);
  assert.match(html, /Create Specification/i);
  assert.doesNotMatch(html, /TechDraw|MeltFlex/i);
});

test("includes the updated brand and workflow assets", async () => {
  const [layout, landing, workspace, measurementTool, projectsOrganizer] = await Promise.all([
    readFile(new URL("../app/layout.tsx", import.meta.url), "utf8"),
    readFile(new URL("../app/page.tsx", import.meta.url), "utf8"),
    readFile(new URL("../app/app/page.tsx", import.meta.url), "utf8"),
    readFile(new URL("../components/FurnitureMeasurementTool.tsx", import.meta.url), "utf8"),
    readFile(new URL("../components/ProjectsOrganizer.tsx", import.meta.url), "utf8"),
  ]);

  assert.match(layout, /og-v2\.png/);
  assert.match(landing, /hero-workflow\.webp/);
  assert.match(workspace, /model-to-drawing/);
  assert.match(workspace, /create-specification/);
  assert.match(measurementTool, /Furniture lasso selection/);
  assert.match(measurementTool, /lasso-selection/);
  assert.match(projectsOrganizer, /Project folders/);
  assert.match(projectsOrganizer, /Move .* to folder/);

  await Promise.all([
    access(new URL("../public/brand/carpdraw-logo-orange.png", import.meta.url)),
    access(new URL("../public/examples/hero-workflow.webp", import.meta.url)),
    access(new URL("../public/og-v2.png", import.meta.url)),
  ]);
});
