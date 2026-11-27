"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { ArrowLeft, CheckCircle2, Circle, FileText, PlayCircle, Sparkles } from "lucide-react";
import { LessonPlayer, type PlayerHandle } from "@/components/learn/player";
import { QuestionCard } from "@/components/learn/question-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Course, Lesson, Question } from "@/lib/api/types";
import { formatDuration } from "@/lib/format";
import { cn } from "@/lib/utils";

type CourseData = {
  course: Course;
  progress: { completed_lessons: number; total_lessons: number; percent: number; next_lesson_id: number | null };
  lessons: Record<number, { watched_percent: number; last_position: number; completed: boolean }>;
};

type LessonData = {
  lesson: Lesson;
  video_tasks: Question[];
  quiz: Question[];
  progress: { watched_percent: number; last_position: number; completed_at: string | null } | null;
  last_attempt: { correct: number; total: number } | null;
};

export function LearnView({ slug }: { slug: string }) {
  const router = useRouter();
  const params = useSearchParams();
  const client = useQueryClient();

  const course = useQuery({
    queryKey: ["learn", slug],
    queryFn: async () => (await apiFetch<{ data: CourseData }>(`/learn/courses/${slug}`)).data,
    retry: false,
  });

  const allLessons = course.data?.course.sections?.flatMap((section) => section.lessons ?? []) ?? [];
  const lessonId = Number(params.get("ders")) || course.data?.progress.next_lesson_id || allLessons[0]?.id;

  if (course.error) {
    const forbidden = course.error instanceof ApiError && course.error.status === 403;
    return (
      <div className="mx-auto max-w-md space-y-4 px-4 py-20 text-center">
        <p className="text-lg font-medium">{forbidden ? "Bu kursa erişimin yok." : "Kurs bulunamadı."}</p>
        <Link href={`/kurslar/${slug}`} className="text-primary hover:underline">
          Kurs sayfasına git
        </Link>
      </div>
    );
  }

  if (!course.data || !lessonId) return <p className="p-10 text-center text-muted-foreground">Yükleniyor…</p>;

  const { progress, lessons } = course.data;
  const refresh = () => client.invalidateQueries({ queryKey: ["learn", slug] });

  return (
    <div className="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[1fr_320px]">
      <div className="min-w-0 space-y-6">
        <Link href="/panel" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" /> Kurslarım
        </Link>
        <LessonPane key={lessonId} lessonId={lessonId} onProgress={refresh} />
      </div>

      <aside className="h-fit space-y-4 rounded-xl border bg-card p-4 lg:sticky lg:top-20">
        <div className="space-y-2">
          <h2 className="font-heading font-semibold">{course.data.course.title}</h2>
          <Progress value={progress.percent} />
          <p className="text-xs text-muted-foreground" data-testid="course-progress">
            {progress.completed_lessons}/{progress.total_lessons} ders tamamlandı · %{progress.percent}
          </p>
        </div>
        {course.data.course.sections?.map((section) => (
          <div key={section.id} className="space-y-1">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">{section.title}</p>
            {section.lessons?.map((lesson) => {
              const done = lessons[lesson.id]?.completed;
              return (
                <button
                  key={lesson.id}
                  onClick={() => router.replace(`/ogren/${slug}?ders=${lesson.id}`)}
                  className={cn(
                    "flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm",
                    lesson.id === lessonId ? "bg-primary/10 font-medium text-primary" : "hover:bg-muted",
                  )}
                >
                  {done ? <CheckCircle2 className="size-4 shrink-0 text-emerald-600" /> : <Circle className="size-4 shrink-0 text-muted-foreground" />}
                  <span className="flex-1">{lesson.title}</span>
                  <span className="text-xs text-muted-foreground">{formatDuration(lesson.duration_seconds)}</span>
                </button>
              );
            })}
          </div>
        ))}
      </aside>
    </div>
  );
}

function LessonPane({ lessonId, onProgress }: { lessonId: number; onProgress: () => void }) {
  const { data } = useQuery({
    queryKey: ["learn", "lesson", lessonId],
    queryFn: async () => (await apiFetch<{ data: LessonData }>(`/learn/lessons/${lessonId}`)).data,
  });

  const player = useRef<PlayerHandle>(null);
  const position = useRef(0);
  const lastBeat = useRef(0);
  const [playing, setPlaying] = useState(false);
  const [answered, setAnswered] = useState<Set<number>>(new Set());
  const [activeTask, setActiveTask] = useState<Question | null>(null);
  const [completed, setCompleted] = useState(false);

  // Heartbeat: report position and the real time watched since the last beat.
  useEffect(() => {
    if (!playing) return;
    lastBeat.current = Date.now();
    const id = window.setInterval(async () => {
      const elapsed = Math.round((Date.now() - lastBeat.current) / 1000);
      lastBeat.current = Date.now();
      const { data: result } = await apiFetch<{ data: { completed: boolean } }>(`/learn/lessons/${lessonId}/progress`, {
        method: "POST",
        body: json({ position: Math.round(position.current), elapsed }),
      });
      if (result.completed) {
        setCompleted(true);
        onProgress();
      }
    }, 15_000);
    return () => window.clearInterval(id);
  }, [playing, lessonId, onProgress]);

  if (!data) return <div className="aspect-video animate-pulse rounded-xl bg-muted" />;

  const { lesson } = data;
  const isVideo = lesson.type === "video";

  const onTime = (seconds: number) => {
    position.current = seconds;
    const due = data.video_tasks.find(
      (task) => task.video_timestamp !== null && seconds >= task.video_timestamp && !answered.has(task.id),
    );
    if (due && !activeTask) {
      player.current?.pause();
      setActiveTask(due);
    }
  };

  const markComplete = async () => {
    await apiFetch(`/learn/lessons/${lessonId}/complete`, { method: "POST" });
    setCompleted(true);
    onProgress();
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="font-heading text-2xl font-bold">{lesson.title}</h1>
        {(completed || data.progress?.completed_at) && <Badge className="bg-emerald-600 text-white">Tamamlandı</Badge>}
      </div>

      {isVideo && (
        <div className="relative">
          <LessonPlayer
            ref={player}
            src={lesson.video_url}
            duration={lesson.duration_seconds}
            startAt={data.progress?.last_position ?? 0}
            onTime={onTime}
            onPlayingChange={setPlaying}
          />
          {activeTask && (
            <div className="absolute inset-0 flex items-center justify-center rounded-xl bg-black/60 p-4" data-testid="video-task">
              <div className="w-full max-w-lg rounded-xl bg-background p-5 shadow-xl">
                <p className="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                  <PlayCircle className="size-4" /> Video görevi
                </p>
                <QuestionCard
                  question={activeTask}
                  context="video"
                  doneLabel="Videoya devam et"
                  onDone={() => {
                    setAnswered((set) => new Set(set).add(activeTask.id));
                    setActiveTask(null);
                    player.current?.play();
                  }}
                />
              </div>
            </div>
          )}
        </div>
      )}

      <article className="space-y-3 rounded-xl border bg-card p-5">
        <h2 className="flex items-center gap-2 font-semibold">
          <FileText className="size-4" /> Ders notu
        </h2>
        <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{lesson.content}</p>
        {!isVideo && !completed && !data.progress?.completed_at && (
          <Button onClick={markComplete} variant="outline">
            <CheckCircle2 /> Tamamladım
          </Button>
        )}
      </article>

      {data.quiz.length > 0 && <Quiz lessonId={lessonId} questions={data.quiz} lastAttempt={data.last_attempt} />}
    </div>
  );
}

type QuizResult = { correct: number; total: number; results: { question_id: number; correct_index: number; explanation: string | null }[] };

function Quiz({ lessonId, questions, lastAttempt }: { lessonId: number; questions: Question[]; lastAttempt: LessonData["last_attempt"] }) {
  const [answers, setAnswers] = useState<Record<number, number>>({});
  const [result, setResult] = useState<QuizResult | null>(null);

  const submit = async () => {
    const { data } = await apiFetch<{ data: QuizResult }>(`/learn/lessons/${lessonId}/quiz`, { method: "POST", body: json({ answers }) });
    setResult(data);
  };

  const byQuestion = new Map(result?.results.map((row) => [row.question_id, row]));

  return (
    <section className="space-y-4 rounded-xl border bg-card p-5" data-testid="quiz">
      <div className="flex items-center justify-between">
        <h2 className="flex items-center gap-2 font-semibold">
          <Sparkles className="size-4 text-primary" /> Ders sonu değerlendirmesi
        </h2>
        {lastAttempt && !result && (
          <span className="text-xs text-muted-foreground">
            Son deneme: {lastAttempt.correct}/{lastAttempt.total}
          </span>
        )}
      </div>
      {questions.map((question, number) => {
        const row = byQuestion.get(question.id);
        return (
          <fieldset key={question.id} className="space-y-2">
            <legend className="text-sm font-medium">
              {number + 1}. {question.stem}
            </legend>
            {question.options.map((option, index) => (
              <label
                key={index}
                className={cn(
                  "flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm",
                  row && index === row.correct_index && "border-emerald-500 bg-emerald-50",
                  row && answers[question.id] === index && index !== row.correct_index && "border-destructive bg-destructive/10",
                )}
              >
                <input
                  type="radio"
                  name={`q-${question.id}`}
                  disabled={!!result}
                  checked={answers[question.id] === index}
                  onChange={() => setAnswers({ ...answers, [question.id]: index })}
                />
                {option}
              </label>
            ))}
            {row?.explanation && <p className="text-xs text-muted-foreground">{row.explanation}</p>}
          </fieldset>
        );
      })}
      {result ? (
        <p className="font-medium" data-testid="quiz-score">
          Sonuç: {result.correct}/{result.total}. Yanlışların tekrar listene eklendi.
        </p>
      ) : (
        <Button onClick={submit} disabled={Object.keys(answers).length < questions.length}>
          Değerlendirmeyi bitir
        </Button>
      )}
    </section>
  );
}
