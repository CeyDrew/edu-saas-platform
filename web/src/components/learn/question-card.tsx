"use client";

import { useState } from "react";
import { CheckCircle2, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { apiFetch, json } from "@/lib/api/client";
import type { Question } from "@/lib/api/types";
import { cn } from "@/lib/utils";

type Feedback = { correct: boolean; correct_index: number; explanation: string | null };

/**
 * One question answered on its own (video task or review card). The server grades it,
 * archives wrong answers and updates the review schedule.
 */
export function QuestionCard({
  question,
  context,
  onDone,
  doneLabel = "Devam et",
}: {
  question: Question;
  context: "video" | "review";
  onDone: (correct: boolean) => void;
  doneLabel?: string;
}) {
  const [selected, setSelected] = useState<number | null>(null);
  const [feedback, setFeedback] = useState<Feedback | null>(null);
  const [startedAt] = useState(() => Date.now());
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (selected === null) return;
    setBusy(true);
    const { data } = await apiFetch<{ data: Feedback }>(`/learn/questions/${question.id}/answer`, {
      method: "POST",
      body: json({ selected_index: selected, context, seconds: Math.round((Date.now() - startedAt) / 1000) }),
    });
    setFeedback(data);
    setBusy(false);
  };

  return (
    <div className="space-y-4" data-testid="question-card">
      <p className="font-medium">{question.stem}</p>
      <div className="grid gap-2">
        {question.options.map((option, index) => {
          const isCorrect = feedback && index === feedback.correct_index;
          const isWrongPick = feedback && index === selected && !feedback.correct;
          return (
            <button
              key={index}
              type="button"
              disabled={!!feedback}
              onClick={() => setSelected(index)}
              className={cn(
                "rounded-lg border px-4 py-2.5 text-left text-sm transition",
                selected === index && !feedback && "border-primary bg-primary/10",
                isCorrect && "border-emerald-500 bg-emerald-50 text-emerald-900",
                isWrongPick && "border-destructive bg-destructive/10",
                !feedback && "hover:bg-muted",
              )}
            >
              {option}
            </button>
          );
        })}
      </div>
      {feedback ? (
        <div className="space-y-3">
          <p className={cn("flex items-center gap-2 text-sm font-medium", feedback.correct ? "text-emerald-600" : "text-destructive")}>
            {feedback.correct ? <CheckCircle2 className="size-4" /> : <XCircle className="size-4" />}
            {feedback.correct ? "Doğru!" : "Yanlış. Bu soru tekrar listene eklendi."}
          </p>
          {feedback.explanation && <p className="text-sm text-muted-foreground">{feedback.explanation}</p>}
          <Button onClick={() => onDone(feedback.correct)}>{doneLabel}</Button>
        </div>
      ) : (
        <Button onClick={submit} disabled={selected === null || busy}>
          Cevapla
        </Button>
      )}
    </div>
  );
}
