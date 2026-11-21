// While an author's page is on its way: the shape of a profile and a few feed
// rows in the warm surface tone, so a tap on a slow phone is answered at once.
// Still, not shimmering: the page is about to arrive, not to entertain.
export default function Loading() {
  return (
    <main aria-busy="true" className="skeleton mx-auto max-w-[720px] px-4">
      <div className="flex flex-col items-center pb-10 pt-12 sm:pt-16">
        <div className="size-24 rounded-full bg-surface" />
        <div className="mt-5 h-8 w-56 rounded-[4px] bg-surface" />
        <div className="mt-4 h-4 w-72 max-w-full rounded-[4px] bg-surface" />
      </div>
      <div className="divide-y divide-line border-t border-line">
        {[0, 1, 2].map((row) => (
          <div key={row} className="flex gap-5 py-7 sm:gap-10">
            <div className="flex-1 space-y-3">
              <div className="h-5 w-4/5 rounded-[4px] bg-surface" />
              <div className="h-4 w-full rounded-[4px] bg-surface" />
              <div className="h-3 w-24 rounded-[4px] bg-surface" />
            </div>
            <div className="size-20 shrink-0 rounded-[4px] bg-surface sm:h-[108px] sm:w-40" />
          </div>
        ))}
      </div>
    </main>
  );
}
