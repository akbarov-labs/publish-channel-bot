// While a post is on its way: its title, byline and a column of text lines at
// the reading measure, so the tap from a feed is answered before the server is.
export default function Loading() {
  return (
    <main aria-busy="true" className="skeleton">
      <div className="mx-auto max-w-[680px] px-4 pt-10 sm:pt-16">
        <div className="h-9 w-11/12 rounded-[4px] bg-surface sm:h-12" />
        <div className="mt-3 h-9 w-2/3 rounded-[4px] bg-surface sm:h-12" />
        <div className="mt-7 flex items-center gap-3">
          <div className="size-11 rounded-full bg-surface" />
          <div className="space-y-2">
            <div className="h-4 w-32 rounded-[4px] bg-surface" />
            <div className="h-3 w-44 rounded-[4px] bg-surface" />
          </div>
        </div>
      </div>
      <div className="mx-auto mt-8 aspect-[16/9] max-w-[960px] bg-surface sm:mt-10 sm:rounded-md" />
      <div className="mx-auto mt-10 max-w-[680px] space-y-4 px-4">
        {["w-full", "w-11/12", "w-full", "w-4/5", "w-full", "w-2/3"].map((width, line) => (
          <div key={line} className={`h-4 rounded-[4px] bg-surface ${width}`} />
        ))}
      </div>
    </main>
  );
}
