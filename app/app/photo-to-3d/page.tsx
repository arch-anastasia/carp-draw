import type { Metadata } from "next";
import { UploadWorkflow } from "@/components/UploadWorkflow";

export const metadata: Metadata = {
  title: "Photo to 3D CAD",
  description: "Prepare an editable 3D CAD concept from an object photo.",
};

export default function PhotoTo3DPage() {
  return <UploadWorkflow mode="model" />;
}
