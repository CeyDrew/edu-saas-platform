"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Bot, CheckCheck, Clock, Loader2, TriangleAlert } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { apiFetch, json } from "@/lib/api/client";
import type { Question } from "@/lib/api/types";
import { cn } from "@/lib/utils";

type Generation = {
  id: number;
  status: "queued" | "running" | "completed" | "failed";
  provider: string;
  model: string;
  requested_count: number;
  questions_created: number;
  attempts: number;
  error: string | null;
  duration_ms: number | null;
  created_at: string;
  lesson: { id: number; title: string; course: string | null } | null;
  drafts: Question[];
};

const statusView = {
  queued: { label: "Kuyrukta", icon: Clock, className: "text-muted-foreground" },
  running: { label: "Üretiliyor", icon: Loader2, className: "text-primary animate-spin" },
  completed: { label: "Tamamlandı", icon: CheckCheck, className: "text-emerald-600" },
  failed: { label: "Başarısız", icon: TriangleAlert, className: "text-destructive" },
};

export default function AiQuestionsPage() {
  const { data } = useQuery({
    queryKey: ["manage", "ai-generations"],
    queryFn: async () => (await apiFetch<{ data: Generation[] }>("/manage/ai-generations")).data,
    refetchInterval: (query) => (query.state.data?.some((g) => g.status === "queued" || g.status === "running") ? 3000 : false),
  });

  return (
    <>
      <PageHeader
        title="AI soru onayı"
        description="Yapay zekânın ders metninden ürettiği sorular taslak olarak gelir; onaylamadan öğrenciler görmez."
      />
      <div className="space-y-4">
        {data?.length === 0 && (
          <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
            Henüz üretim yok. Kurs düzenleme ekranında bir dersin “Sorular” penceresinden başlatabilirsin.
          </p>
        )}
        {data?.map((generation) => <GenerationCard key={generation.id} generation={generation} />)}
      </div>
    </>
  );
}

function GenerationCard({ generation }: { generation: Generation }) {
  const client = useQueryClient();
  const [selected, setSelected] = useState<Set<number>>(() => new Set(generation.drafts.map((d) => d.id)));
  const view = statusView[generation.status];
  const Icon = view.icon;

  const review = useMutation({
    mutationFn: () => apiFetch<{ data: { approved: number; rejected: number } }>(`/manage/ai-generations/${generation.id}/review`, {
      method: "POST",
      body: json({ approve: [...selected] }),
    }),
    onSuccess: ({ data }) => {
      toast.success(`${data.approved} soru yayınlandı, ${data.rejected} soru reddedildi.`);
      client.invalidateQueries({ queryKey: ["manage"] });
    },
  });

  return (
    <div className="space-y-4 rounded-xl border bg-card p-5" data-testid="ai-generation">
      <div className="flex flex-wrap items-center gap-3">
        <Bot className="size-5 text-primary" />
        <div className="flex-1">
          <p className="font-medium">{generation.lesson?.title}</p>
          <p className="text-xs text-muted-foreground">
            {generation.lesson?.course} · {generation.provider}/{generation.model}
            {generation.duration_ms ? ` · ${(generation.duration_ms / 1000).toFixed(1)} sn` : ""}
            {generation.attempts > 1 ? ` · ${generation.attempts} deneme` : ""}
          </p>
        </div>
        <span className="flex items-center gap-1.5 text-sm">
          <Icon className={cn("size-4", view.className)} /> {view.label}
        </span>
      </div>

      {generation.error && <p className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{generation.error}</p>}

      {generation.drafts.length > 0 && (
        <>
          <ul className="space-y-3">
            {generation.drafts.map((draft) => (
              <li key={draft.id} className="flex gap-3 rounded-lg border p-3">
                <Checkbox
                  checked={selected.has(draft.id)}
                  aria-label="Yayınla"
                  onCheckedChange={(checked) =>
                    setSelected((set) => {
                      const next = new Set(set);
                      if (checked) next.add(draft.id);
                      else next.delete(draft.id);
                      return next;
                    })
                  }
                />
                <div className="flex-1 space-y-1.5 text-sm">
                  <p className="font-medium">{draft.stem}</p>
                  <ol className="grid gap-1 sm:grid-cols-2">
                    {draft.options.map((option, index) => (
                      <li key={index} className={cn("rounded px-2 py-1", index === draft.correct_index ? "bg-emerald-50 text-emerald-900" : "bg-muted/50")}>
                        {String.fromCharCode(65 + index)}) {option}
                      </li>
                    ))}
                  </ol>
                  {draft.explanation && <p className="text-xs text-muted-foreground">{draft.explanation}</p>}
                  {draft.video_timestamp !== null && <Badge variant="outline">Video görevi · {draft.video_timestamp}. sn</Badge>}
                </div>
              </li>
            ))}
          </ul>
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">
              {selected.size}/{generation.drafts.length} seçili · seçilmeyenler reddedilir
            </span>
            <Button onClick={() => review.mutate()} disabled={review.isPending}>
              Seçilenleri yayınla
            </Button>
          </div>
        </>
      )}
      {generation.status === "completed" && generation.drafts.length === 0 && (
        <p className="text-sm text-muted-foreground">Bu üretimin taslakları incelendi.</p>
      )}
    </div>
  );
}
