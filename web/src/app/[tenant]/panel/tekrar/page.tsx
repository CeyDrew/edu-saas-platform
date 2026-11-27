"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { CalendarClock, PartyPopper, Sparkles } from "lucide-react";
import { QuestionCard } from "@/components/learn/question-card";
import { PageHeader, StatCard } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { apiFetch } from "@/lib/api/client";
import type { Question } from "@/lib/api/types";
import { formatDate } from "@/lib/format";

type DueItem = { id: number; question: Question; lesson: string; course: string; repetitions: number };
type Mistake = {
  id: number;
  question: Question;
  lesson: string;
  course: string;
  wrong_count: number;
  interval_days: number;
  due_at: string;
  mastered: boolean;
};

export default function ReviewPage() {
  return (
    <>
      <PageHeader title="Tekrar listem" description="Yanlış yaptığın sorular aralıklı tekrar (SM-2) ile karşına çıkar." />
      <Advice />
      <Tabs defaultValue="today">
        <TabsList>
          <TabsTrigger value="today">Bugünün tekrarı</TabsTrigger>
          <TabsTrigger value="archive">Yanlış arşivi</TabsTrigger>
        </TabsList>
        <TabsContent value="today" className="pt-4">
          <Today />
        </TabsContent>
        <TabsContent value="archive" className="pt-4">
          <Archive />
        </TabsContent>
      </Tabs>
    </>
  );
}

type Advice = { summary: string; focus: { lesson: string; reason: string }[]; generated_by: string };

function Advice() {
  const { data } = useQuery({
    queryKey: ["review", "advice"],
    queryFn: async () => (await apiFetch<{ data: Advice }>("/learn/recommendation")).data,
    staleTime: 10 * 60_000,
  });

  if (!data) return null;

  return (
    <div className="mb-6 space-y-2 rounded-xl border border-primary/30 bg-primary/5 p-4" data-testid="advice">
      <p className="flex items-center gap-2 text-sm font-semibold">
        <Sparkles className="size-4 text-primary" /> Çalışma önerin
        <span className="text-xs font-normal text-muted-foreground">({data.generated_by === "rules" ? "kural tabanlı" : `AI · ${data.generated_by}`})</span>
      </p>
      <p className="text-sm">{data.summary}</p>
      {data.focus.length > 0 && (
        <ul className="list-inside list-disc text-sm text-muted-foreground">
          {data.focus.map((item) => (
            <li key={item.lesson}>
              <strong className="text-foreground">{item.lesson}</strong>: {item.reason}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function Today() {
  const client = useQueryClient();
  const { data } = useQuery({
    queryKey: ["review", "due"],
    queryFn: () => apiFetch<{ data: DueItem[]; meta: { due_today: number; upcoming: number } }>("/learn/review"),
  });
  const [index, setIndex] = useState(0);
  const [score, setScore] = useState({ correct: 0, total: 0 });

  if (!data) return <p className="text-muted-foreground">Yükleniyor…</p>;

  const item = data.data[index];

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Bugün" value={data.meta.due_today} hint="tekrar edilecek soru" />
        <StatCard label="Bu oturum" value={`${score.correct}/${score.total}`} hint="doğru" />
        <StatCard label="İleri tarihli" value={data.meta.upcoming} hint="planlanmış tekrar" />
      </div>
      {item ? (
        <div className="max-w-2xl space-y-3 rounded-xl border bg-card p-5" key={item.id}>
          <p className="text-xs text-muted-foreground">
            {item.course} · {item.lesson} · {index + 1}/{data.data.length}
          </p>
          <QuestionCard
            question={item.question}
            context="review"
            doneLabel={index + 1 < data.data.length ? "Sonraki soru" : "Bitir"}
            onDone={(correct) => {
              setScore((s) => ({ correct: s.correct + Number(correct), total: s.total + 1 }));
              if (index + 1 < data.data.length) setIndex(index + 1);
              else {
                setIndex(0);
                client.invalidateQueries({ queryKey: ["review"] });
              }
            }}
          />
        </div>
      ) : (
        <div className="flex max-w-2xl items-center gap-3 rounded-xl border border-dashed p-8 text-muted-foreground">
          <PartyPopper className="size-6 text-primary" /> Bugünlük tekrar tamam. Yarın yeni sorular seni bekliyor.
        </div>
      )}
    </div>
  );
}

function Archive() {
  const { data } = useQuery({
    queryKey: ["review", "mistakes"],
    queryFn: () => apiFetch<{ data: Mistake[]; meta: { total: number } }>("/learn/mistakes"),
  });

  return (
    <div className="space-y-3">
      <p className="text-sm text-muted-foreground">{data?.meta.total ?? "…"} soru arşivde</p>
      {data?.data.map((item) => (
        <div key={item.id} className="space-y-2 rounded-xl border bg-card p-4">
          <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span>
              {item.course} · {item.lesson}
            </span>
            <Badge variant="outline">{item.wrong_count} yanlış</Badge>
            {item.mastered ? (
              <Badge className="bg-emerald-600 text-white">Öğrenildi</Badge>
            ) : (
              <span className="ml-auto flex items-center gap-1">
                <CalendarClock className="size-3.5" /> Sonraki tekrar: {formatDate(item.due_at)}
              </span>
            )}
          </div>
          <p className="font-medium">{item.question.stem}</p>
          <p className="text-sm">
            Doğru cevap: <strong>{item.question.options[item.question.correct_index ?? 0]}</strong>
          </p>
          {item.question.explanation && <p className="text-sm text-muted-foreground">{item.question.explanation}</p>}
        </div>
      ))}
    </div>
  );
}
