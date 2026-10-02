import Link from "next/link";
import { SITE_NAME } from "@/lib/site";

/** The slim bar on the root domain's pages. */
export default function SiteBar() {
  return (
    <header className="border-b border-line">
      <div className="mx-auto flex h-14 max-w-[1120px] items-center px-4 sm:px-6">
        <Link href="/" className="text-[1.0625rem] font-bold tracking-[-0.02em]">
          {SITE_NAME}
        </Link>
      </div>
    </header>
  );
}
