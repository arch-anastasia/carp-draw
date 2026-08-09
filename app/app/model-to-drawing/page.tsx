import type { Metadata } from "next";
import { UploadWorkflow } from "@/components/UploadWorkflow";

export const metadata: Metadata = {
  title: "3D Model to Drawing",
  description: "Prepare technical drawing views from a 3D model file.",
};

export default function ModelToDrawingPage() {
  return <UploadWorkflow mode="modelDrawing" />;
}
