/** Wraps the search words in a text with <mark>, case-insensitively. */
export default function Highlight({ text, words }: { text: string; words?: string[] }) {
  const terms = (words ?? []).map((word) => word.trim()).filter((word) => word.length >= 2);
  if (terms.length === 0) return <>{text}</>;

  const pattern = new RegExp(`(${terms.map((term) => term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")).join("|")})`, "giu");
  const parts = text.split(pattern);

  return (
    <>
      {parts.map((part, index) =>
        index % 2 === 1 ? (
          <mark key={index} className="search-hit">
            {part}
          </mark>
        ) : (
          part
        ),
      )}
    </>
  );
}
