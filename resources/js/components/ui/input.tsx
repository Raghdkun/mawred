import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        // DSActions: 40 px, 10 px radius, input border, hover darker border, focus ring halo.
        "flex h-10 w-full min-w-0 rounded-md border border-input bg-card px-3 text-base text-foreground transition-[border-color,box-shadow] duration-150 ease-soft outline-none placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground hover:border-input-hover file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-disabled disabled:text-disabled-foreground md:text-sm",
        "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30",
        "aria-invalid:border-danger aria-invalid:focus-visible:ring-danger/25",
        className
      )}
      {...props}
    />
  )
}

export { Input }
