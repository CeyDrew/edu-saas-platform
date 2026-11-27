"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { CircleHelp, Clock, Sparkles, Trash2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Lesson, Question } from "@/lib/api/types";

const empty = { stem: "", options: ["", "", "", ""], correct_index: 0, explanation: "", video_timestamp: "" };

export function QuestionsDialog({ lesson }: { lesson: Lesson }) {
  const client = useQueryClient();
  const key = ["manage", "questions", lesson.id];
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(empty);

  const { data } = useQuery({
    queryKey: key,
    enabled: open,
    queryFn: async () => (await apiFetch<{ data: Question[] }>(`/manage/lessons/${lesson.id}/questions`)).data,
  });

  const refresh = () => client.invalidateQueries({ queryKey: key });

  const create = useMutation({
    mutationFn: () =>
      apiFetch(`/manage/lessons/${lesson.id}/questions`, {
        method: "POST",
        body: json({
          ...form,
          explanation: form.explanation || null,
          video_timestamp: form.video_timestamp === "" ? null : Number(form.video_timestamp),
        }),
      }),
    onSuccess: () => {
      setForm(empty);
      refresh();
      toast.success("Soru eklendi");
    },
    onError: (error) => toast.error(error instanceof ApiError ? (Object.values(error.fieldErrors())[0] ?? error.message) : "Hata"),
  });

  const [count, setCount] = useState(5);
  const generate = useMutation({
    mutationFn: () => apiFetch(`/manage/lessons/${lesson.id}/ai-questions`, { method: "POST", body: json({ count }) }),
    onSuccess: () => toast.success("Soru üretimi kuyruğa alındı. Taslaklar “AI soru onayı” sayfasına düşecek."),
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Hata"),
  });

  const remove = useMutation({
    mutationFn: (id: number) => apiFetch(`/manage/questions/${id}`, { method: "DELETE" }),
    onSuccess: refresh,
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button variant="ghost" size="xs" />}>
        <CircleHelp /> Sorular
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{lesson.title} · Soru bankası</DialogTitle>
        </DialogHeader>

        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm">
          <Sparkles className="size-4 text-primary" />
          <span className="flex-1">Ders metninden yapay zekâ ile taslak soru üret</span>
          <select className="h-7 rounded-md border bg-background px-2" value={count} onChange={(e) => setCount(Number(e.target.value))} aria-label="Soru sayısı">
            {[3, 5, 10].map((n) => (
              <option key={n} value={n}>
                {n} soru
              </option>
            ))}
          </select>
          <Button size="sm" onClick={() => generate.mutate()} disabled={generate.isPending}>
            AI ile üret
          </Button>
        </div>

        <ul className="space-y-2">
          {data?.map((question) => (
            <li key={question.id} className="flex gap-3 rounded-lg border p-3 text-sm">
              <div className="flex-1 space-y-1">
                <p className="font-medium">{question.stem}</p>
                <p className="text-muted-foreground">Doğru: {question.options[question.correct_index ?? 0]}</p>
                <div className="flex gap-1">
                  {question.video_timestamp !== null && (
                    <Badge variant="outline">
                      <Clock /> Video görevi · {Math.floor(question.video_timestamp / 60)}:{String(question.video_timestamp % 60).padStart(2, "0")}
                    </Badge>
                  )}
                  {question.source === "ai" && <Badge variant="secondary">AI</Badge>}
                  {question.status !== "approved" && <Badge variant="outline">{question.status}</Badge>}
                </div>
              </div>
              <Button variant="ghost" size="icon-xs" aria-label="Soruyu sil" onClick={() => remove.mutate(question.id)}>
                <Trash2 />
              </Button>
            </li>
          ))}
          {data?.length === 0 && <li className="text-sm text-muted-foreground">Bu derste soru yok.</li>}
        </ul>

        <form
          className="space-y-3 rounded-lg border bg-muted/30 p-4"
          onSubmit={(event) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <p className="text-sm font-semibold">Yeni soru</p>
          <Textarea placeholder="Soru metni" value={form.stem} onChange={(e) => setForm({ ...form, stem: e.target.value })} required />
          {form.options.map((option, index) => (
            <label key={index} className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="correct"
                checked={form.correct_index === index}
                onChange={() => setForm({ ...form, correct_index: index })}
                aria-label={`Doğru şık ${index + 1}`}
              />
              <Input
                placeholder={`${index + 1}. şık`}
                value={option}
                required
                onChange={(e) => setForm({ ...form, options: form.options.map((o, i) => (i === index ? e.target.value : o)) })}
              />
            </label>
          ))}
          <Input placeholder="Açıklama (isteğe bağlı)" value={form.explanation} onChange={(e) => setForm({ ...form, explanation: e.target.value })} />
          {lesson.type === "video" && (
            <div className="space-y-1.5">
              <Label htmlFor="ts">Video görevi saniyesi (boş bırakılırsa ders sonu sorusu)</Label>
              <Input
                id="ts"
                type="number"
                min={0}
                max={lesson.duration_seconds}
                value={form.video_timestamp}
                onChange={(e) => setForm({ ...form, video_timestamp: e.target.value })}
              />
            </div>
          )}
          <Button type="submit" disabled={create.isPending}>
            Soruyu ekle
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}
