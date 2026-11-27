import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { RequireAuth } from "@/components/auth/require-auth";
import { CheckoutForm } from "./checkout-form";

export const metadata: Metadata = { title: "Ödeme", robots: { index: false } };

export default async function CheckoutPage({ searchParams }: PageProps<"/[tenant]/odeme">) {
  const query = await searchParams;
  const course = typeof query.kurs === "string" ? query.kurs : undefined;
  const plan = typeof query.plan === "string" ? Number(query.plan) : undefined;
  if (!course && !plan) notFound();

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="mb-6 font-heading text-2xl font-bold">Ödeme</h1>
      <Suspense>
        <RequireAuth>
          <CheckoutForm product={course ? { course } : { plan_id: plan }} />
        </RequireAuth>
      </Suspense>
    </div>
  );
}
