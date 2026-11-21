import { ExternalLink } from "@/components/Icons";
import type { PostMedium } from "@/lib/api";
import type { Dictionary } from "@/lib/i18n";

function fileName(url: string): string {
  try {
    return decodeURIComponent(new URL(url).pathname.split("/").pop() ?? url);
  } catch {
    return url;
  }
}

/** Audio tracks get a player each; other files are listed for download. */
export default function Attachments({ media, t }: { media: PostMedium[]; t: Dictionary }) {
  const audio = media.filter((item) => item.type === "audio");
  const files = media.filter((item) => item.type === "document");
  if (audio.length === 0 && files.length === 0) return null;

  return (
    <div className="space-y-6">
      {audio.length > 0 && (
        <ul aria-label={t.audio} className="space-y-3">
          {audio.map((item) => (
            <li key={item.id} className="rounded-md bg-surface p-3">
              <p className="mb-2 truncate px-1 text-[0.8125rem] text-muted">{fileName(item.url)}</p>
              <audio controls preload="metadata" src={item.url} className="w-full" />
            </li>
          ))}
        </ul>
      )}
      {files.length > 0 && (
        <section aria-label={t.files}>
          <h2 className="text-[0.9375rem] font-semibold">{t.files}</h2>
          <ul className="mt-2 divide-y divide-line border-y border-line">
            {files.map((item) => (
              <li key={item.id}>
                <a
                  href={item.url}
                  download
                  className="flex items-center justify-between gap-4 py-3 text-[0.9375rem] hover:underline hover:underline-offset-4"
                >
                  <span className="truncate">{fileName(item.url)}</span>
                  <ExternalLink className="size-4 shrink-0 text-muted" />
                </a>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
