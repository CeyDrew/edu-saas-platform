"use client";

import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from "react";
import { Pause, Play } from "lucide-react";
import { Button } from "@/components/ui/button";

export type PlayerHandle = { play: () => void; pause: () => void };

type Props = {
  src?: string | null;
  duration: number;
  startAt?: number;
  onTime: (seconds: number) => void;
  onPlayingChange?: (playing: boolean) => void;
};

function clock(seconds: number) {
  const s = Math.floor(seconds);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
}

/**
 * Lesson player. Uses the real video when the lesson has one; seeded demo lessons have
 * no files, so a simulated timeline (same controls, same events) stands in for them.
 */
export const LessonPlayer = forwardRef<PlayerHandle, Props>(function LessonPlayer(
  { src, duration, startAt = 0, onTime, onPlayingChange },
  ref,
) {
  const video = useRef<HTMLVideoElement>(null);
  const [time, setTime] = useState(startAt);
  const timeRef = useRef(startAt);
  const [playing, setPlaying] = useState(false);
  const [speed, setSpeed] = useState(1);

  const setPlayingState = (value: boolean) => {
    setPlaying(value);
    onPlayingChange?.(value);
  };

  useImperativeHandle(ref, () => ({
    play: () => (src ? video.current?.play() : setPlayingState(true)),
    pause: () => (src ? video.current?.pause() : setPlayingState(false)),
  }));

  // Simulated clock.
  useEffect(() => {
    if (src || !playing) return;
    const id = window.setInterval(() => {
      // Side effects stay outside the state updater: onTime may pause the player.
      const next = Math.min(duration, timeRef.current + speed);
      timeRef.current = next;
      setTime(next);
      onTime(next);
      if (next >= duration) setPlayingState(false);
    }, 1000);
    return () => window.clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [src, playing, speed, duration]);

  if (src) {
    return (
      <video
        ref={video}
        src={src}
        controls
        className="aspect-video w-full rounded-xl bg-black"
        onLoadedMetadata={(e) => (e.currentTarget.currentTime = startAt)}
        onTimeUpdate={(e) => onTime(e.currentTarget.currentTime)}
        onPlay={() => onPlayingChange?.(true)}
        onPause={() => onPlayingChange?.(false)}
      />
    );
  }

  return (
    <div className="brand-gradient flex aspect-video w-full flex-col justify-end overflow-hidden rounded-xl text-white">
      <div className="flex flex-1 items-center justify-center">
        <button
          aria-label={playing ? "Duraklat" : "Oynat"}
          onClick={() => setPlayingState(!playing)}
          className="flex size-16 items-center justify-center rounded-full bg-black/30 backdrop-blur transition hover:bg-black/40"
        >
          {playing ? <Pause className="size-7" /> : <Play className="size-7 translate-x-0.5" />}
        </button>
      </div>
      <div className="space-y-2 bg-black/30 p-3">
        <input
          type="range"
          aria-label="Zaman çizelgesi"
          min={0}
          max={duration}
          value={time}
          onChange={(e) => {
            const value = Number(e.target.value);
            timeRef.current = value;
            setTime(value);
            onTime(value);
          }}
          className="w-full accent-white"
        />
        <div className="flex items-center justify-between text-xs">
          <span data-testid="player-time">
            {clock(time)} / {clock(duration)}
          </span>
          <span className="flex items-center gap-1">
            <span className="opacity-70">Demo video ·</span>
            {[1, 2, 8].map((value) => (
              <Button
                key={value}
                size="xs"
                variant="ghost"
                className={value === speed ? "bg-white/25 text-white" : "text-white/80 hover:bg-white/10 hover:text-white"}
                onClick={() => setSpeed(value)}
              >
                {value}x
              </Button>
            ))}
          </span>
        </div>
      </div>
    </div>
  );
});
