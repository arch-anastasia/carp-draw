import type { Metadata } from "next";
import { UploadWorkflow } from "@/components/UploadWorkflow";

export const metadata: Metadata = {
  title: "Photo to Drawing",
  description: "Prepare a technical drawing concept from a product photo.",
};

export default function PhotoToDrawingPage() {
  return <UploadWorkflow mode="drawing" />;
}
