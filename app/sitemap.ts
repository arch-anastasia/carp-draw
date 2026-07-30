import type { MetadataRoute } from "next";

export default function sitemap(): MetadataRoute.Sitemap {
  const baseUrl =
    process.env.NEXT_PUBLIC_SITE_URL || "https://carpdraw.ai";
  const routes = [
    "",
    "/contact",
    "/privacy",
    "/app",
    "/app/photo-to-drawing",
    "/app/drawing-to-photo",
    "/app/photo-to-3d",
    "/app/projects",
    "/app/plans",
  ];

  return routes.map((route) => ({
    url: `${baseUrl}${route}`,
    lastModified: new Date("2026-07-29"),
    changeFrequency: route === "" ? "weekly" : "monthly",
    priority: route === "" ? 1 : route.startsWith("/app/") ? 0.7 : 0.8,
  }));
}
