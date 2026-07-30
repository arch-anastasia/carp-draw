import type { Metadata } from "next";
import { UploadWorkflow } from "@/components/UploadWorkflow";

export const metadata: Metadata = {
  title: "Drawing to Photo",
  description: "Prepare a product render brief from a technical drawing.",
};

export default function DrawingToPhotoPage() {
  return <UploadWorkflow mode="render" />;
}
