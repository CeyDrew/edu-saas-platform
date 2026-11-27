import type { Metadata } from "next";
import Link from "next/link";
import { Check } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { api } from "@/lib/api/server";
import { formatPrice } from "@/lib/format";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "Abonelik" };

type Plan = { id: number; name: string; description: string | null; price: number; interval_months: number };

export default async function SubscriptionPage({ params }: PageProps<"/[tenant]/abonelik">) {
  const { tenant } = await params;
  const plans = await api<{ data: Plan[] }>("/plans", { tenant });

  return (
    <div className="mx-auto max-w-4xl space-y-8 px-4 py-14 text-center">
      <div className="space-y-2">
        <h1 className="font-heading text-3xl font-bold">Tek abonelik, tüm kurslar</h1>
        <p className="text-muted-foreground">İstediğin zaman iptal et; dönem sonuna kadar erişimin devam eder.</p>
      </div>
      <div className="mx-auto grid max-w-md gap-6">
        {plans.data.map((plan) => (
          <div key={plan.id} className="space-y-5 rounded-2xl border-2 border-primary bg-card p-8 text-left shadow-lg">
            <div>
              <h2 className="font-heading text-xl font-semibold">{plan.name}</h2>
              <p className="text-sm text-muted-foreground">{plan.description}</p>
            </div>
            <p>
              <span className="font-heading text-4xl font-bold">{formatPrice(plan.price)}</span>
              <span className="text-muted-foreground"> / ay</span>
            </p>
            <ul className="space-y-2 text-sm">
              {["Abonelik kapsamındaki tüm kurslar", "Video görevleri ve testler", "Kişisel tekrar planı", "Otomatik aylık yenileme"].map(
                (item) => (
                  <li key={item} className="flex items-center gap-2">
                    <Check className="size-4 text-primary" /> {item}
                  </li>
                ),
              )}
            </ul>
            <Link href={`/odeme?plan=${plan.id}`} className={cn(buttonVariants({ size: "lg" }), "h-11 w-full")}>
              Abone ol
            </Link>
          </div>
        ))}
      </div>
    </div>
  );
}
