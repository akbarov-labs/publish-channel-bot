"use client";

import { useEffect, useRef, useState } from "react";
import { Pause, Play } from "@/components/Icons";
import { dict, type Locale } from "@/lib/i18n";

function clock(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds < 0) return "0:00";
  const whole = Math.floor(seconds);
  return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, "0")}`;
}

/**
 * A track or a voice note from the channel, in the site's own voice: one
 * play button, a hairline you can drag, and the time in tabular figures.
 * The native <audio> element still does the playing.
 */
export default function AudioPlayer({ src, title, locale }: { src: string; title: string; locale: Locale }) {
  const t = dict(locale);
  const audio = useRef<HTMLAudioElement>(null);
  const [playing, setPlaying] = useState(false);
  const [current, setCurrent] = useState(0);
  const [duration, setDuration] = useState(0);
  const progress = duration > 0 ? (current / duration) * 100 : 0;

  // The metadata can arrive before hydration, when the handler below has no
  // listener yet; read the length directly in that case.
  useEffect(() => {
    const element = audio.current;
    if (element && element.readyState >= 1 && Number.isFinite(element.duration)) setDuration(element.duration);
  }, []);

  const toggle = () => {
    const element = audio.current;
    if (!element) return;
    if (element.paused) void element.play();
    else element.pause();
  };

  return (
    <div className="flex items-center gap-3.5 rounded-md bg-surface p-3 sm:gap-4 sm:p-4">
      <button
        type="button"
        onClick={toggle}
        aria-label={playing ? t.pause : t.play}
        className="inline-flex size-11 shrink-0 items-center justify-center rounded-full bg-ink text-bg transition-transform duration-150 ease-out hover:scale-105 active:scale-95"
      >
        {playing ? <Pause className="size-5" /> : <Play className="size-5 translate-x-px" />}
      </button>
      <div className="min-w-0 flex-1">
        <p className="truncate text-[0.8125rem] font-medium">{title}</p>
        <input
          type="range"
          min={0}
          max={duration || 0}
          step={0.1}
          value={current}
          onChange={(event) => {
            const value = Number(event.target.value);
            if (audio.current) audio.current.currentTime = value;
            setCurrent(value);
          }}
          aria-label={t.seek}
          aria-valuetext={`${clock(current)} / ${clock(duration)}`}
          style={{ "--progress": `${progress}%` } as React.CSSProperties}
          className="audio-range mt-2 w-full"
        />
      </div>
      <span className="shrink-0 text-[0.8125rem] text-muted tabular-nums">
        {clock(current)}
        <span className="hidden sm:inline"> / {clock(duration)}</span>
      </span>
      <audio
        ref={audio}
        src={src}
        preload="metadata"
        onLoadedMetadata={(event) => setDuration(event.currentTarget.duration)}
        onDurationChange={(event) => setDuration(event.currentTarget.duration)}
        onTimeUpdate={(event) => setCurrent(event.currentTarget.currentTime)}
        onPlay={() => setPlaying(true)}
        onPause={() => setPlaying(false)}
        onEnded={() => setPlaying(false)}
      />
    </div>
  );
}
