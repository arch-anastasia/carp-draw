import type { Metadata } from "next";
import { UploadWorkflow } from "@/components/UploadWorkflow";

export const metadata: Metadata = {
  title: "Create Specification",
  description: "Prepare a structured product or project specification.",
};

export default function CreateSpecificationPage() {
  return <UploadWorkflow mode="specification" />;
}
