import type { Metadata } from "next";
import { Suspense } from "react";
import { RequireAuth } from "@/components/auth/require-auth";
import { LearnView } from "./learn-view";

export const metadata: Metadata = { title: "Ders", robots: { index: false } };

export default async function LearnPage({ params }: PageProps<"/[tenant]/ogren/[slug]">) {
  const { slug } = await params;

  return (
    <Suspense>
      <RequireAuth>
        <LearnView slug={slug} />
      </RequireAuth>
    </Suspense>
  );
}
